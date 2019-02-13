FROM python:2-alpine

# Install:
#  - expect for installing the interactive EnergyPlus installer

# Copy the current directory contents into the container at /tmp
RUN apk add --no-cache bash gcompat libstdc++ curl jq coreutils && \
    wget -q -O /tmp/install.sh https://github.com/NREL/EnergyPlus/releases/download/v8.5.0/EnergyPlus-8.5.0-c87e61b44b-Linux-x86_64.sh && \
    chmod +x /tmp/install.sh && \
    sed -i 's/install_directory=""/install_directory=\$default_install_directory/g' /tmp/install.sh && \
    sed -i 's/link_directory=""/link_directory=\$default_link_directory/g' /tmp/install.sh && \
    yes | /tmp/install.sh && \
    /bin/bash -c 'energyplus --version' && \
    rm -f /tmp/install.sh

COPY ./helio.bash /usr/local/bin/helio

RUN addgroup -S epuser && adduser -S epuser -G epuser && chmod +x /usr/local/bin/helio

USER epuser

WORKDIR '/tmp'

CMD ["helio"]

