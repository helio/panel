#!/bin/bash

# Variable Init
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
    echo "ERROR ${1} during Execution ${@:2}"
    echo "Details: ${ERROR}"
    exit ${1}
}

SUCCESS() {
    if [[ -n "${@}" ]]; then
        echo "Done: ${@}"
    fi
    exit 0
}

WARN_ON_ERROR() {
    if [ -n "${ERROR}" ]; then
        echo "Warning: ${ERROR} ${@}"
        ERROR=''
    fi
}

FAIL_ON_ERROR() {
    if [ -n "${ERROR}" ]; then
        FAIL ${@}
    fi
}

# API Functions
GET_CURRENT_TASK_ID() {
    echo $(echo "${TASK_CONFIG}" | jq -r '.id')
}
GET_CURRENT_TASK_IDFSUM() {
    echo $(echo "${TASK_CONFIG}" | jq -r '.idf_sha1')
}
GET_CURRENT_TASK_EPWSUM() {
    echo $(echo "${TASK_CONFIG}" | jq -r '.epw_sha1')
}

GET_EXEC_URL() {
    if [ -n "$(GET_CURRENT_TASK_ID)" ]; then
        TASK_PART="&taskid=$(GET_CURRENT_TASK_ID)"
    fi
    # NOTE: taskid HAS TO BE the last parameter, otherwise the healthcheck won't work.
    echo "${HELIO_URL}${@}?jobid=${HELIO_JOBID}&token=${HELIO_TOKEN}${TASK_PART}"
}
GET_CURRENT_TASK_IDFURL() {
    echo $(echo "${TASK_CONFIG}" | jq -r '.idf')
}
GET_CURRENT_TASK_EPWURL() {
    echo $(echo "${TASK_CONFIG}" | jq -r '.epw')
}
GET_CURRENT_UPLOAD_URL() {
    echo $(GET_EXEC_URL $(echo "${TASK_CONFIG}" | jq -r '.upload'))
}
GET_CURRENT_REPORT_URL() {
    echo $(GET_EXEC_URL $(echo "${TASK_CONFIG}" | jq -r '.report'))
}

SET_CURRENT_TASK_CONFIG() {
    DEBUG "Setting Task Config from $(GET_EXEC_URL "exec/work/getnextinqueue")"
    TASK_CONFIG=$(curl -fsSL -X GET -H "Accept: application/json" $(GET_EXEC_URL "exec/work/getnextinqueue")) || return 1
    DEBUG "Done Setting Task Config: ${TASK_CONFIG}"

    # write TaskID to file because hearbeat subprocess doesn't have ENV available.
    GET_CURRENT_TASK_ID > /tmp/taskId
}

UPDATE_SOURCE_FILES() {
    DEBUG "Updating Source Files"

    CURRENT_IDF_FILENAME="${WORKDIR_ROOT}/$(GET_CURRENT_TASK_IDFSUM).idf"
    if [ ! -f ${CURRENT_IDF_FILENAME} ]; then
        if echo -n "$(GET_CURRENT_TASK_IDFURL)" | sha1sum --status -c <(echo "$(GET_CURRENT_TASK_IDFSUM)  -"); then
            curl -fsSLo ${CURRENT_IDF_FILENAME} $(GET_CURRENT_TASK_IDFURL) || return 1
        else
            echo "shasum of $(GET_CURRENT_TASK_IDFURL) => $(GET_CURRENT_TASK_IDFSUM) failed"
            return 1
        fi
    fi

    CURRENT_EPW_FILENAME="${WORKDIR_ROOT}/$(GET_CURRENT_TASK_EPWSUM).epw"
    if [ ! -f ${CURRENT_EPW_FILENAME} ]; then
        if echo -n "$(GET_CURRENT_TASK_EPWURL)" | sha1sum --status -c <(echo "$(GET_CURRENT_TASK_EPWSUM)  -"); then
            curl -fsSLo ${CURRENT_EPW_FILENAME} $(GET_CURRENT_TASK_EPWURL) || return 1
        else
            echo "shasum of $(GET_CURRENT_TASK_EPWURL) => $(GET_CURRENT_TASK_EPWSUM) failed"
            return 1
        fi
    fi

    DEBUG "Done Updating Source Files ${CURRENT_IDF_FILENAME} and ${CURRENT_EPW_FILENAME}"
}

UPLOAD_RESULT() {
    DEBUG "uploading to $(GET_CURRENT_UPLOAD_URL)"
    if [[ $(GET_CURRENT_UPLOAD_URL) = "rsync://"* ]]; then
        rsync -ravz Output/* $(cut -b 9- <(echo "$(GET_CURRENT_UPLOAD_URL)")) || return 1
    fi
    if [[ $(GET_CURRENT_UPLOAD_URL) = "http"* ]]; then
        tar czf $(GET_CURRENT_TASK_ID).tar.gz Output && rm -rf Output || return 1
        curl -fsSLo /dev/null -X POST -F "file=@${@}" $(GET_CURRENT_UPLOAD_URL) || return 1
    fi
    DEBUG "upload done"
}

REPORT() {
    DEBUG "reporting success to $(GET_CURRENT_REPORT_URL)"
    echo '{"success":"'${1}'","epw_stat":"'$(wc ${CURRENT_EPW_FILENAME})'","idf_stat":"'$(wc ${CURRENT_IDF_FILENAME})'", "started":"'${STARTED}'", "ended":"'$(date +%s)'","taskid":"'$(GET_CURRENT_TASK_ID)'"}' > /tmp/report
    DEBUG "report data: " $(cat /tmp/report | tr -d '[:space:]')
    curl -fsSLo /dev/null -X PUT -H "Content-Type: application/json" --data @/tmp/report "$(GET_CURRENT_REPORT_URL)&XDEBUG_SESSION_START=true" || return 1
}

# Subshell heartbeat
HEARTBEAT() {
    while true; do
        if [ -f /tmp/taskId ]; then
            DEBUG "Hearbeat"
            if [ -n "$(cat /tmp/taskId | tr -d '[:space:]')" ]; then
                curl -fsSLo /dev/null -X PUT $(GET_EXEC_URL "exec/heartbeat")\&taskid=$(cat /tmp/taskId | tr -d '[:space:]')
            fi
            DEBUG "Heartbeat done"
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
FAIL_ON_ERROR 1547196288 "Can't do anything without proper ENV set."


# The Script
HEARTBEAT &
while SET_CURRENT_TASK_CONFIG; do
    STARTED=$(date +%s)
    UPDATE_SOURCE_FILES                                           || FAIL 1547196071 "Couldn't update Source Files"
    runenergyplus ${CURRENT_IDF_FILENAME} ${CURRENT_EPW_FILENAME} || FAIL 1547196078 "Error during runenergyplus: ${?}"
    UPLOAD_RESULT "${WORKDIR_ROOT}/$(GET_CURRENT_TASK_ID).tar.gz" || FAIL 1547196087 "Couldn't upload result file"
    REPORT "Done"                                                 || FAIL 1547196091 "Report failed"

    WARN_ON_ERROR "Error during Execution of Task $(GET_CURRENT_TASK_ID)"
done

SUCCESS "done with all the work."