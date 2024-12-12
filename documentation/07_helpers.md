[Back to Menu](../README.md#documentation)<a id='top'></a>
### **Helpers**

#### Description

This component provides helpers to manage tenant-specific environments.

On folder deploy/scripts are the bash scripts which lead to help manage the operations for multi-tenant environment.

### **Commands**

- `tenant-context [command]` the bash command call laralord application to retrieve the environment variables list and apply
  it as environment variables for command
  ##### Usages:
  `TENANT_ID=12345 tenant-context php artisan migrate` - run the migration for Tenant with ID 23432432423
  
  `TENANT_ID=12345 tenant-context php artisan tinker` - run the tinker on TENANT_ID context. Could be usefully for debug.

  `TENANT_ID=12345 tenant-context bash` - run bush on tenant context.

  > **WARNING** don't use the direct environment interpolation on the command - because it will lead to wrong result
  >
  > <span style="color:orange">Wrong usage</span> 
  >
  > `TENANT_ID=12345 tenant-context echo $APP_NAME` - will return empty line because the $APP_NAME env variable doesn't exists on the context for command call
  >
  > <span style="color:green">Correct usage</span>
  > 
  >  ``TENANT_ID=12345 tenant-context eval 'echo $APP_NAME'`` - will return the tenant's $APP_NAME from env variables

- `tenants-iterate [command]` - bash command which iterate the command for each tenant, one by one.
  ##### Usages:
  `tenants-iterate php artisan migrate` - run the migration for each tenant
 
  `tenants-iterate tenant-bootstrap-script.sh` - run custom script for each tenant


- `laralord env:list` - list of tenant's keys for multi-tenant environment
  ##### Usages:
  ```sh
    $ laralord env:list
    33434234324324
    23432434435455
    32432432423432
    23432432432324
    $
    ``` 
  check tenants-iterate script as usage code base 


[Menu](../README.md#documentation) &nbsp; &nbsp;  [Top](#top)
