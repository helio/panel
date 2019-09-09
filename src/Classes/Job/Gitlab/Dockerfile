FROM gitlab/gitlab-runner

RUN sed -i 's%\(^exec.*$\)%sudo gitlab-runner register --non-interactive --url "${GITLAB_URL:?}" --registration-token "${GITLAB_TOKEN:?}" --executor "docker" --docker-image alpine:latest --description "${GITLAB_RUNNER_NAME:?}" --tag-list "${GITLAB_TAGS:-helio,docker}" --run-untagged="true" --locked="false" --access-level="not_protected"\n\1%' /entrypoint