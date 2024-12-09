#!/bin/bash

set -e

if [[ -n "${TENANT_ID}" ]]; then
    echo "RUNNING IN TENANT #${TENANT_ID} CONTEXT";
    tenant-context "$@"

    exit $?
fi

"$@"
