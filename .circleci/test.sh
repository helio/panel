#!/usr/bin/env bash

##########
# Guards and Variables
if [[ -z "${TESTUSER_TOKEN}" ]]; then exit 1; fi
BASE_URL=${1:-https://panel.idling.host}
ID=test-$(date "+%s")
DATA='{"type":"busybox","jobname":"_test","billingReference":"'${ID}'"}'

##########
# Create the Job and wait for its successful provisioning
JOB=$(curl -fsSL -m 360 -X POST -d ${DATA} -H "Authorization: Bearer ${TESTUSER_TOKEN}" "${BASE_URL}/api/job")
if [[ -z "${JOB}" ]]; then exit 2; fi

JOB_ID=$(echo ${JOB} | jq -r .id)
JOB_TOKEN=$(echo ${JOB} | jq -r .token)

TIMEOUT=500
echo -ne "Waiting for job ready state. This may take a while"
while true; do
    if [[ 0 -ge ${TIMEOUT} ]]; then
        echo "Job never got created"
        exit 1;
    fi
    if curl -fsL -o /dev/null -H "Authorization: Bearer ${JOB_TOKEN}" "${BASE_URL}/api/job/isready?id=${JOB_ID}"; then
        echo " Job is ready!"
        break
    fi
    echo -ne "."
    TIMEOUT=$(expr ${TIMEOUT} - 30)
    sleep 30;
done


##########
# Execute job and wait for it to be done
curl -fsSL -o /dev/null -X POST -d '{"env":[{"limit":"42"}]}' -H "Authorization: Bearer ${JOB_TOKEN}" "${BASE_URL}/api/job/${JOB_ID}/execute" || exit 2

TIMEOUT=400
echo -ne "Waiting for job to be completed. This may take a while"
while true; do
    if [[ 0 -ge ${TIMEOUT} ]]; then
        echo "Job never executed completely"
        exit 1;
    fi
    if curl -fsL -o /dev/null -d '{"id":"'${JOB_ID}'"}' -H "Authorization: Bearer ${JOB_TOKEN}" "${BASE_URL}/api/job/isdone"; then
        echo " Job was executed!"
        break
    fi
    echo -ne "."
    TIMEOUT=$(expr ${TIMEOUT} - 15)
    sleep 15;
done


##########
#TODO: Add result and log check here


##########
# Delete Job and wait for its disappearance
curl -fsSL -m 360 -X DELETE -H "Authorization: Bearer ${TESTUSER_TOKEN}" "${BASE_URL}/api/job" || exit 2

TIMEOUT=300
echo -ne "Waiting for job to be completed. This may take a while"
while true; do
    if [[ 0 -ge ${TIMEOUT} ]]; then
        echo "Job never was deleted properly"
        exit 1;
    fi
    if !curl -fsL -o /dev/null -H "Authorization: Bearer ${JOB_TOKEN}" "${BASE_URL}/api/job?id=${JOB_ID}"; then
        echo " Job was deleted!"
        break
    fi
    echo -ne "."
    TIMEOUT=$(expr ${TIMEOUT} - 15)
    sleep 15;
done

echo 'done';
exit 0;
