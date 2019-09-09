#!/bin/bash

trap 'exit 0' SIGTERM SIGINT SIGKILL

# Variable Init
ERROR=''
WORKDIR_ROOT=$(pwd -P)
EXECUTION_CONFIG=''
CURRENT_IDF_FILENAME=''
CURRENT_EPW_FILENAME=''


# functions
DEBUG() {
    if [[ -n "${DEBUG}" ]]; then
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
    if [[ -n "${ERROR}" ]]; then
        echo "Warning: ${ERROR} ${@}"
        ERROR=''
    fi
}

FAIL_ON_ERROR() {
    if [[ -n "${ERROR}" ]]; then
        FAIL ${@}
    fi
}

# API Functions
GET_CURRENT_EXECUTION_ID() {
    echo $(echo "${EXECUTION_CONFIG}" | jq -r '.id')
}
GET_CURRENT_EXECUTION_IDFSUM() {
    echo $(echo "${EXECUTION_CONFIG}" | jq -r '.idf_sha1')
}
GET_CURRENT_EXECUTION_EPWSUM() {
    echo $(echo "${EXECUTION_CONFIG}" | jq -r '.epw_sha1')
}

GET_EXEC_URL() {
    if [[ -n "$(GET_CURRENT_EXECUTION_ID)" ]]; then
        EXECUTION_PART="?id=$(GET_CURRENT_EXECUTION_ID)"
    fi
    # NOTE: executionid HAS TO BE the last parameter, otherwise the healthcheck won't work.
    echo "${HELIO_URL}api/job/${HELIO_JOBID}/execute${@}"
}
GET_CURRENT_EXECUTION_IDFURL() {
    echo $(echo "${EXECUTION_CONFIG}" | jq -r '.idf')
}
GET_CURRENT_EXECUTION_EPWURL() {
    echo $(echo "${EXECUTION_CONFIG}" | jq -r '.epw')
}
GET_CURRENT_UPLOAD_URL() {
    echo $(GET_EXEC_URL $(echo "${EXECUTION_CONFIG}" | jq -r '.upload'))
}

SET_CURRENT_EXECUTION_CONFIG() {
    DEBUG "Setting Execution Config from $(GET_EXEC_URL "/work/getnextinqueue")"
    while true; do
        EXECUTION_CONFIG=$(curl -fsSL -X GET -H "Authorization: Bearer ${HELIO_TOKEN}" -H "Accept: application/json" $(GET_EXEC_URL "/work/getnextinqueue")) && break

        if [[ -z "${KEEP_ALIVE}" ]]; then
            DEBUG "Config update failed. KEEP_ALIVE not set. Exitting."
            return 1
        fi

        DEBUG "Currently, there's nothing to do. Waiting..."
        sleep 30
        DEBUG "Retrying..."
    done
    DEBUG "Done Setting Execution Config: ${EXECUTION_CONFIG}"

    # write ExecutionID to file because hearbeat subprocess doesn't have ENV available.
    GET_CURRENT_EXECUTION_ID > /tmp/executionId
}

UPDATE_SOURCE_FILES() {
    DEBUG "Updating Source Files"

    CURRENT_IDF_FILENAME="${WORKDIR_ROOT}/$(GET_CURRENT_EXECUTION_IDFSUM).idf"
    if [[ ! -f ${CURRENT_IDF_FILENAME} ]]; then
        if echo -n "$(GET_CURRENT_EXECUTION_IDFURL)" | sha1sum --status -c <(echo "$(GET_CURRENT_EXECUTION_IDFSUM)  -"); then
            if [[  $(GET_CURRENT_EXECUTION_IDFURL) = "http"* ]]; then
                curl -fsSLo ${CURRENT_IDF_FILENAME} $(GET_CURRENT_EXECUTION_IDFURL) || return 1
            else
                curl -H "Authorization: Bearer ${HELIO_TOKEN}" -fsSLo ${CURRENT_IDF_FILENAME} $(GET_EXEC_URL $(GET_CURRENT_EXECUTION_IDFURL)) || return 1
            fi
        else
            echo "shasum of $(GET_CURRENT_EXECUTION_IDFURL) => $(GET_CURRENT_EXECUTION_IDFSUM) failed"
            return 1
        fi
    fi

    CURRENT_EPW_FILENAME="${WORKDIR_ROOT}/$(GET_CURRENT_EXECUTION_EPWSUM).epw"
    if [[ ! -f ${CURRENT_EPW_FILENAME} ]]; then
        if echo -n "$(GET_CURRENT_EXECUTION_EPWURL)" | sha1sum --status -c <(echo "$(GET_CURRENT_EXECUTION_EPWSUM)  -"); then
            if [[  $(GET_CURRENT_EXECUTION_EPWURL) = "http"* ]]; then
                curl -fsSLo ${CURRENT_EPW_FILENAME} $(GET_CURRENT_EXECUTION_EPWURL) || return 1
            else
                curl -H "Authorization: Bearer ${HELIO_TOKEN}" -fsSLo ${CURRENT_EPW_FILENAME} $(GET_EXEC_URL $(GET_CURRENT_EXECUTION_EPWURL)) || return 1
            fi
        else
            echo "shasum of $(GET_CURRENT_EXECUTION_EPWURL) => $(GET_CURRENT_EXECUTION_EPWSUM) failed"
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
        tar czf $(GET_CURRENT_EXECUTION_ID).tar.gz Output && rm -rf Output || return 1
        curl -H "Authorization: Bearer ${HELIO_TOKEN}" -fsSLo /dev/null -X POST -F "file=@${@}" $(GET_CURRENT_UPLOAD_URL) || return 1
    fi
    DEBUG "upload done"
}

REPORT() {
    DEBUG "reporting success to ${REPORT_URL}"
    # note: unfourtuntately, due to whitespaces and potential linebreaks, we have to go through a file
    echo '{"success":"'${1}'","epw_stat":"'$(wc ${CURRENT_EPW_FILENAME})'","idf_stat":"'$(wc ${CURRENT_IDF_FILENAME})'", "started":"'${STARTED}'", "ended":"'$(date +%s)'","executionid":"'$(GET_CURRENT_EXECUTION_ID)'"}' > /tmp/report
    DEBUG "report data: " $(cat /tmp/report | tr -d '[:space:]')
    curl -H "Authorization: Bearer ${HELIO_TOKEN}" -fsSLo /dev/null -X PUT -H "Content-Type: application/json" --data @/tmp/report "${REPORT_URL}" && rm -f /tmp/report || return 1
}

# Subshell heartbeat
HEARTBEAT() {
    while true; do
        if [[ -f /tmp/executionId ]]; then
            DEBUG "Hearbeat"
            if [[ -n "$(cat /tmp/executionId | tr -d '[:space:]')" ]]; then
                curl -H "Authorization: Bearer ${HELIO_TOKEN}" -fsSLo /dev/null -X PUT ${HEARTBEAT_URL}\&executionid=$(cat /tmp/executionId | tr -d '[:space:]')
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
while SET_CURRENT_EXECUTION_CONFIG; do
    STARTED=$(date +%s)
    UPDATE_SOURCE_FILES                                                 || FAIL 1547196071 "Couldn't update Source Files"
    runenergyplus ${CURRENT_IDF_FILENAME} ${CURRENT_EPW_FILENAME}       || FAIL 1547196078 "Error during runenergyplus: ${?}"
    UPLOAD_RESULT "${WORKDIR_ROOT}/$(GET_CURRENT_EXECUTION_ID).tar.gz"  || FAIL 1547196087 "Couldn't upload result file"
    REPORT "Done"                                                       || FAIL 1547196091 "Report failed"

    WARN_ON_ERROR "Error during Execution of Execution $(GET_CURRENT_EXECUTION_ID)"
done

SUCCESS "done with all the work."
