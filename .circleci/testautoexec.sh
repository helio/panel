#!/usr/bin/env bash

##########
# Guards and Variables
BASE_URL=${1:-https://panel.idling.host}
ID=${2:-10563}
TOKEN=${3:-${TESTUSER_TOKEN}}
if [[ -z "${TOKEN}" ]]; then echo "Error: Token not set" && exit 1; fi

##########
# Get job status
STATUS=$(curl -fsSL -X GET -H "Authorization: Bearer ${TOKEN}" "${BASE_URL}/api/job?id=${ID}")
if [[ -z "${STATUS}" ]]; then echo "Error: Status not valid" && exit 2; fi

LATEST_JOB_RUN=$(echo ${STATUS} | jq -r .executions.newestRunTime)

if [[ -z "${LATEST_JOB_RUN}" || 0 -ge ${LATEST_JOB_RUN} ]]; then echo "Error: Job has never run" && exit 3; fi

# Test run is scheduled in a way that the job has to have run in the last hour.
# If this fails, make sure to synchronize autoExecSchedule and circleCi Schedule
EXPECTED=$(expr $(date "+%s") - 3600 )

if [[ ${EXPECTED} -ge ${LATEST_JOB_RUN} ]]; then echo "Error: Latest Execution is to old" && exit 4; fi

echo "done"
exit 0
