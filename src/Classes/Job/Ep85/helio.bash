#!/bin/bash

# Variable Init
RETURN=0
ERROR=''
WORKDIR_ROOT=$(pwd -P)
TASK_CONFIG=''
CURRENT_IDF_FILENAME=''
CURRENT_EPW_FILENAME=''


# functions
DEBUG() {
    if [ -n "${DEBUG}" ]; then
        echo "$@"
    fi
}

FAIL() {
    echo "ERROR ${1} during Execution: ${@:2}"
    exit ${1}
}

SUCCESS() {
    if [[ -n "${@}" ]]; then
        echo "Done: ${@}"
    fi
    exit 0
}

FAIL_ON_ERROR() {
    if [ ${RETURN} -gt 0 ]; then
        FAIL "${RETURN} ${ERROR} ${@}"
    fi
    if [ -n "${ERROR}" ]; then
        FAIL 2 "${ERROR} ${@}"
    fi
}

GET_EXEC_URL() {
    echo "${HELIO_URL}${@}?jobid=${HELIO_JOBID}&token=${HELIO_TOKEN}"
}
GET_CURRENT_TASK_ID() {
    echo $(echo "${TASK_CONFIG}" | jq -r '.id')
}
GET_CURRENT_TASK_IDFURL() {
    echo $(GET_EXEC_URL $(echo "${TASK_CONFIG}" | jq -r '.idf'))
}
GET_CURRENT_TASK_IDFSUM() {
    echo $(echo "${TASK_CONFIG}" | jq -r '.idf_sha1')
}
GET_CURRENT_TASK_EPWURL() {
    echo $(GET_EXEC_URL $(echo "${TASK_CONFIG}" | jq -r '.epw'))
}
GET_CURRENT_TASK_EPWSUM() {
    echo $(echo "${TASK_CONFIG}" | jq -r '.epw_sha1')
}
GET_CURRENT_UPLOAD_URL() {
    echo $(GET_EXEC_URL $(echo "${TASK_CONFIG}" | jq -r '.upload'))
}
GET_CURRENT_REPORT_URL() {
    echo $(GET_EXEC_URL $(echo "${TASK_CONFIG}" | jq -r '.report'))
}

SET_CURRENT_TASK_CONFIG() {
    DEBUG "Setting Stask Config"
    TASK_CONFIG=$(curl -fsSL -X GET -H "Accept: application/json" $(GET_EXEC_URL "exec/work/getnextinqueue") 2>/dev/null) || return 1

    # write TaskID to file because hearbeat subprocess doesn't have ENV available.
    GET_CURRENT_TASK_ID > /tmp/taskId

    DEBUG "Done Setting Task Config: ${TASK_CONFIG}"
}

UPDATE_SOURCE_FILES() {
    DEBUG "Updating Source Files"
    CURRENT_IDF_FILENAME="${WORKDIR_ROOT}/$(GET_CURRENT_TASK_IDFSUM).idf"
    if [ ! -f ${CURRENT_IDF_FILENAME} ]; then
        wget -q -O ${CURRENT_IDF_FILENAME} $(GET_CURRENT_TASK_IDFURL) || FAIL 5 "Couldn't download IDF file"
    fi
    CURRENT_EPW_FILENAME="${WORKDIR_ROOT}/$(GET_CURRENT_TASK_EPWSUM).epw"
    if [ ! -f ${CURRENT_EPW_FILENAME} ]; then
        wget -q -O ${CURRENT_EPW_FILENAME} $(GET_CURRENT_TASK_EPWURL) || FAIL 5 "Couldn't download EPW file"
    fi
    DEBUG "Done Updating Source Files ${CURRENT_IDF_FILENAME} and ${CURRENT_EPW_FILENAME}"
}

UPLOAD_RESULT() {
    DEBUG "uploading to $(GET_CURRENT_UPLOAD_URL)"
    curl -fsSL -X POST -F "file=@${@}" $(GET_CURRENT_UPLOAD_URL) || FAIL "Upload failed for task $(GET_CURRENT_TASK_ID)"
}

REPORT() {
    curl -fsSL -X PUT -H "Content-Type: application/json" -d '{"success":"'${1}'","taskid":"'$(GET_CURRENT_TASK_ID)'"}' $(GET_CURRENT_REPORT_URL)
}

HEARTBEAT() {
    while true; do
        if [ -f /tmp/taskId ]; then
            DEBUG "Hearbeat $(GET_EXEC_URL "exec/heartbeat/$(cat /tmp/taskId | tr '[:space:]')")"
            if [ -n "$(cat /tmp/taskId | tr '[:space:]')" ]; then
                curl -fsSL -X PUT $(GET_EXEC_URL "exec/heartbeat/$(cat /tmp/taskId | tr '[:space:]')") || echo "hb failed"
            fi
        fi
        sleep 1
    done
}



# Guards
if [[ -z "${HELIO_JOBID}" ]]; then
    ERROR="${ERROR}Helio Job ID not set! "
fi

if [[ -z "${HELIO_TOKEN}" ]]; then
    ERROR="${ERROR}Helio Authentication Token not set! "
fi

if [[ -z "${HELIO_URL}" || "${HELIO_URL}" != "https://"* || "${HELIO_URL}" != *"/" ]]; then

    # allow non-ssl on local development
    if [[ "${HELIO_URL}" != *"localhost"* ]]; then
        ERROR="${ERROR}Helio URL not properly set!"
    fi
fi
FAIL_ON_ERROR "Can't do anything without proper ENV set."


# The Script
HEARTBEAT &
while SET_CURRENT_TASK_CONFIG; do
    UPDATE_SOURCE_FILES
    runenergyplus ${CURRENT_IDF_FILENAME} ${CURRENT_EPW_FILENAME} || ERROR="Error during runenergyplus: ${?}"
    tar czvf $(GET_CURRENT_TASK_ID).tar.gz Output && rm -rf Output

    UPLOAD_RESULT "${WORKDIR_ROOT}/$(GET_CURRENT_TASK_ID).tar.gz" || ERROR="Couldn't upload result file"

    REPORT "Done"

    FAIL_ON_ERROR "Error during Execution of Task $(GET_CURRENT_TASK_ID)"
done

SUCCESS "done with all the work."