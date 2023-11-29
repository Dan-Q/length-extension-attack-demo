FROM ubuntu:18.04
RUN apt update && apt install -y git make gcc openssl libssl-dev
RUN git clone https://github.com/iagox86/hash_extender && cd hash_extender && make && mv hash_extender /usr/local/sbin/hash_extender
