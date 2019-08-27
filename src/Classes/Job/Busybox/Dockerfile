FROM busybox

ENV LIMIT=5 HELIO_TOKEN="" REPORT_URL=""

ENTRYPOINT ["/bin/sh", "-c"]
CMD ["set -e; i=0; while [ \"$i\" -lt \"${LIMIT}\" ]; do echo \"$i: $(date)\"; i=$((i+1)); sleep 10; done; wget -q --header \"Authorization: Bearer $HELIO_TOKEN\" $REPORT_URL"]
