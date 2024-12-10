#!/bin/bash

# Function to initialize tenant-specific environment
initialize_tenant() {
  echo "Loading tenant $TENANT_ID env variables"
  local tenant_id="$1"

  if [[ -z "$tenant_id" ]]; then
    echo "Error: Tenant ID is not provided."
    return 1
  fi

  export TENANT_ID="$tenant_id"
  export ENV_FILE="/tmp/.env.${TENANT_ID}"
  export ENV_VAULT_KEY="${TENANT_ID}"
  export ENV_TENANT_ID="${TENANT_ID}"
  #  export SERVER_MODE="single"

  #  echo "Sourcing Tenant ${TENANT_ID} credentials to file $ENV_FILE"
  if ! laralord env:store --log-level=error --vault_key="$TENANT_ID" --env_file="$ENV_FILE"; then
    echo "Failed to retrieve tenant environment credentials."
    return 1
  fi

  while IFS= read -r line; do
    # Skip lines that are comments or empty
    [[ "$line" =~ ^#.*$ || -z "$line" ]] && continue

    # Split into key and value
    key=$(echo "$line" | cut -d'=' -f1)
    # escape back-quotes symbols
    value=$(echo "$line" | cut -d'=' -f2- | sed 's/`/\\`/g')

    # Trim the double-quotes
    if [[ "$value" =~ ^\".*\"$ ]]; then
      value="${value:1:-1}" # Remove first and last character (the quotes)
    fi

    # Applying interpolation to the value
    eval "value=\"$value\""

    # Check if the variable is readonly
    if readonly_vars=$(declare -r | awk '{print $3}' | grep -w "$key"); then
      echo "Skipping readonly variable: $key"
      continue
    fi

    # Exporting the key-value pair
    export "$key=$value"
  done <"$ENV_FILE"

  echo "Tenant $TENANT_ID env variables loaded"

  return 0
}
