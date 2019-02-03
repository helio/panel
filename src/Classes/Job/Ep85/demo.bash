#!/usr/bin/env bash


if [[ "${1}" = "-h" || "${1}" = "--help" ]]; then
    echo "usage:"
    echo "./demo.bash <path_To_idf_file> <job-id> <token> <optional: url to epw file>"
    echo "";
    echo "example:"
    echo "./demo.bash /tmp/demo.idf 3 263700aa:60d891d454f416a53667c5284c9164f75cc5a617"
    exit 0
fi

EPW=${4:-https://energyplus.net/weather-download/south_america_wmo_region_3/CHL//CHL_Concepcion.856820_IWEC/CHL_Concepcion.856820_IWEC.epw}

curl -fsSLo /dev/null -X POST  -F "idf=@${1}" -F "run_id=demorun from $(uname -n)" -F 'report_url=empty' -F "epw=${EPW}" "http://localhost:8099/exec?jobid=${2}&token=${3}"