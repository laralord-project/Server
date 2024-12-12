[Back to Menu](../README.md#documentation)<a id='top'></a>
### **Environment Source and Resolvers**

The commands `queue`, `scheduler`, and `server` support the `ENV_SOURCE` environment variable (`--envSource` option). This variable determines the source of environment variables and can be one of the following:

####    1. `ENV_SOURCE=vault`
Fetches credentials from Vault HashiCorp and supports the following configurations:

For Production environment we recommend to use auth type **kubernetes** - because this approach avoid the token's TTL management. 

Kubernetes automatically set the fresh token on pods - which could be used for Vault Authorization  

| Environment Variable         | Option                          | Default Value                                   | Description                                                         |  
|------------------------------|---------------------------------|-----------------------------------------------|---------------------------------------------------------------------|  
| `ENV_VAULT_ADDR`             | `--vaultAddr=http://vault:8200` | `http://vault:8200`                           | Address of the Vault HashiCorp server.                              |  
| `ENV_VAULT_TOKEN`            | `--vaultToken=""`              | `""`                                          | Token for Vault HashiCorp authentication.                           |  
| `ENV_VAULT_STORAGE`          | `--vaultStorage=secrets`       | `secrets`                                     | KV storage path in Vault HashiCorp.                                 |  
| `ENV_VAULT_PREFIX`           | `--vaultPrefix=""`             | `""`                                          | Key prefix for environments in Vault KV storage.                    |  
| `ENV_VAULT_KEY`              | `--vaultKey=secrets`           | `secrets`                                     | Secret key in Vault for single-mode operation.                      |  
| `ENV_VAULT_AUTH_TYPE`        | `--vaultAuthType=""`           | `""`                                          | Authentication type for Vault: `token`, `kubernetes`.               |  
| `ENV_VAULT_AUTH_ENDPOINT`    | `--vaultAuthEndpoint=kubernetes` | `kubernetes`                                | Authentication endpoint for Vault.                                   |  
| `ENV_VAULT_SA_TOKEN_PATH`    | `--vaultSaTokenPath=/var/run/secrets/kubernetes.io/serviceaccount/token` | `/var/run/secrets/kubernetes.io/serviceaccount/token` | Vault service account token path for Kubernetes.          |  
| `ENV_VAULT_AUTH_ROLE`        | `--vaultAuthRole=""`           | `""`                                          | Authentication role for Kubernetes in Vault.                        |  
| `ENV_VAULT_UPDATE_PERIOD`    | `--vaultUpdatePeriod=1`        | `1`                                           | Period in minutes to refresh secrets; `0` disables updates.         |  
| `ENV_FILE`                   | `--envFile=./.env`             | `./.env`                                      | Path to the project's `.env` file for storing updated variables.    |  
| `ENV_FILE_UPDATE`            | `--envFileUpdate=true`         | `false`                                       | Specifies whether the `.env` file should be updated on Vault secret updates. |  


#### 2. `ENV_SOURCE=dir`
Tenants Environment variables stored on .env files on local directory. DotEnv file name format are following **.env.[tenant-id]**

Supports multi-tenant environments, useful for local development.

After start workers, environment resolver listen for updated for the directory and reload variables on some file changed.

Configurations include:

| Environment Variable | Option            | Default Value | Description                                                          |  
|----------------------|-------------------|---------------|----------------------------------------------------------------------|  
| `ENV_DIR`            | `--envsDir=./.envs` | `./.envs`   | Directory containing `.env` files. Required when `ENV_SOURCE` is `dir`. |  


#### 3. `ENV_SOURCE=file`
Supports a single-tenant environment. This configuration directly references the path to a `.env` file.
Automatically reload workers on file change, no need to restart server.

| Environment Variable | Option            | Default Value | Description                                      |  
|----------------------|-------------------|---------------|------------------------------------------------|  
| `ENV_FILE`           | `--envFile=./.env` | `./.env`     | Path to the project's `.env` file.             |  

[Menu](../README.md#documentation) &nbsp; &nbsp;  [Top](#top)
