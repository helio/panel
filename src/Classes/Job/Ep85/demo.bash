#!/usr/bin/env bash


if [[ "${1}" = "-h" || "${1}" = "--help" ]]; then
    echo "usage:"
    echo "./demo.bash <User API token (createt at panel.idling.host/profile)>"
    echo "";
    echo "example:"
    echo "./demo.bash 246f04cf:a4454d1d9b39c3e0a142d09cb2f57c0a2295b261"
    exit 0
fi

if [[ -z "${1}" ]]; then exit 1; fi

BASE_URL=${2:-http://localhost:8099}
ID=$(date -j -f "%a %b %d %T %Z %Y" "`date`" "+%s")
JOB=$(curl -fsSL -m 360 -X POST -H "Authorization: Bearer ${1}" "${BASE_URL}/api/job/add?jobtype=ep85&jobname=_auto&billingReference=${ID}")
JOB_ID=$(echo ${JOB} | jq -r .id)
JOB_TOKEN=$(echo ${JOB} | jq -r .token)

while true; do
    PROGRES="${PROGRES}."
    if curl -fsSL -o /dev/null -H "Authorization: Bearer ${JOB_TOKEN}" "${BASE_URL}/api/job/isready?jobid=${JOB_ID}"; then
        break
    fi
    echo -ne "\\rWaiting for job ready state. This may take a while${PROGRES}"
    sleep 15;
done


TOTAL=5
RUNS=${TOTAL}

if [[ -z "${JOB_ID}" || -z "${JOB_TOKEN}" ]]; then exit 1; fi


while [ ${RUNS} -gt 0 ]; do
    EPW=https://energyplus.net/weather-download/south_america_wmo_region_3/CHL//CHL_Concepcion.856820_IWEC/CHL_Concepcion.856820_IWEC.epw
    IDF=https://pastebin.com/raw/zj2SAV5Z

    DATA='{"run_id":"demorun_from_'$(uname -n)'","report_url":"rsync://user@target","epw":"'${EPW}'","idf":"'${IDF}'"}'

    curl -fsSLo /dev/null -X POST -d ${DATA} -H "Authorization: Bearer ${JOB_TOKEN}" "${BASE_URL}/api/job/${JOB_ID}/execute"

    RUNS=$[${RUNS}-1]
done


while true; do
    if curl -fsSL -o /dev/null -H "Authorization: Bearer ${JOB_TOKEN}" "${BASE_URL}/api/job/${JOB_ID}/execute/isdone"; then
        exit 0
    fi
    sleep 30;
done