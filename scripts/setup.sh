#!/bin/bash
#
# Basic setup for a working local env
#
# WIP ;)

## Create index
curl -X "PUT" "http://localhost:9200/log_user_1"

## Create document
curl -X "POST" "http://localhost:9200/log_user_1/_doc/" \
     -H 'Content-Type: application/json; charset=utf-8' \
     -d $'{
  "job_id": "74",
  "source": "stdout",
  "@timestamp": "2019-08-20T12:16:02.000Z",
  "HELIO_EXECUTIONID": "1",
  "HELIO_JOBID": "1",
  "container_id": "yolo",
  "log": "test stdout",
  "container_name": "yoloname"
}'
