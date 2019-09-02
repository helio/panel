#!/usr/bin/env bash

set -e

##########
# Guards and Variables
BASE_URL=${1:-https://panel.idling.host}
TOKEN=${2:-${TESTUSER_TOKEN}}
ID=test-$(date "+%s")
DATA='{"type":"busybox","name":"_test","billingReference":"'${ID}'"}'

call_api() {
  local method=$1
  local token=$2
  local path=$3
  shift 3

  curl -fL -m 60 -X "$method" -H "Authorization: Bearer $token" "${BASE_URL}$path" "$@"
}

delete_job() {
  call_api "DELETE" "$2" "/api/job?id=$1" -sS -o /dev/null

  TIMEOUT=300
  echo -ne "Waiting for job $1 to be deleted. This may take a while"
  while true; do
      if [[ 0 -ge ${TIMEOUT} ]]; then
          echo " timeout"
          echo "Job never was deleted properly"
          exit 20;
      fi
      STATUS=$(call_api "GET" "$2" "/api/job?id=$0" -s)
      STATUS_CODE=$(echo "${STATUS}" | jq -r .status)
      if [[ 9 -eq ${STATUS_CODE} ]]; then
          echo " Job was deleted!"
          break
      fi
      echo -ne "."
      TIMEOUT=$(( TIMEOUT - 15))
      sleep 15;
  done
}

if [[ -z "${TOKEN}" ]]; then
  echo "Missing env variable TOKEN"
  exit 1;
fi

##########
# Create the Job and wait for its successful provisioning
JOB=$(call_api "POST" "${TOKEN}" "/api/job" -d "${DATA}" -H 'Content-Type: application/json' -sS)
if [[ -z "${JOB}" ]]; then
  echo "Create job response empty"
  exit 2;
fi

JOB_ID=$(echo "${JOB}" | jq -r .id)
JOB_TOKEN=$(echo "${JOB}" | jq -r .token)

finish() {
  delete_job "$JOB_ID" "$JOB_TOKEN"
}

# ensure job gets deleted on exit
trap finish EXIT

TIMEOUT=500
echo -ne "Waiting for job ${JOB_ID} ready state. This may take a while"
while true; do
    if [[ 0 -ge ${TIMEOUT} ]]; then
        echo " timeout"
        echo "Job never got created"
        exit 3;
    fi

    if call_api "GET" "$JOB_TOKEN" "/api/job/isready?id=${JOB_ID}" -s -o /dev/null; then
        echo " Job is ready!"
        break
    fi
    echo -ne "."
    TIMEOUT=$(( TIMEOUT - 30))
    sleep 30;
done


##########
# Execute job and wait for it to be done
call_api "POST" "$JOB_TOKEN" "/api/job/${JOB_ID}/execute" -H 'Content-Type: application/json' -d '{"env":[{"LIMIT":"7"}]}' -o /dev/null -sS || exit 4

TIMEOUT=400
echo -ne "Waiting for job ${JOB_ID} to be completed. This may take a while"
while true; do
    if [[ 0 -ge ${TIMEOUT} ]]; then
        echo " timeout"
        echo "Job never executed completely"
        exit 5;
    fi

    if call_api "GET" "$JOB_TOKEN" "/api/job/isdone?id=${JOB_ID}" -s -o /dev/null; then
        echo " Job was executed!"
        break
    fi
    echo -ne "."
    TIMEOUT=$(expr ${TIMEOUT} - 15)
    sleep 15;
done

echo
echo "Logs"
echo "=============================="
call_api "GET" "$JOB_TOKEN" "/api/job/logs?id=${JOB_ID}" -sS | jq -r '.logs[] | [.timestamp,.message] | @tsv'
echo "=============================="
echo

echo "done";
echo
exit 0;
