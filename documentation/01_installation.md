[Back to Menu](../README.md#documentation)<a id='top'></a>
### **Installation**

1. #### **Composer install**

   ``$ composer require laralord-project/server``

   ```
    # to run the server use the command
    $ ./vendor/bin/laralord server:start
   ```
   > **WARNING** we don't recommend this method  because composer will install all required dependencies to build the server which is actually not required for compiled work
2. #### **Download Binary File**
    ```
    $ curl https://github.com/laralord-project/Server/releases/download/v0.1.0/laralord \ 
          && chmod +x laralord && mv laralord /usr/bin/laralord
   ```
3. #### **Docker Image**

   [Docker image](https://hub.docker.com/r/laralordproject/server) include the bash helpers which could be used on the tenants provisioning and management
   
   Docker Registry are on [https://hub.docker.com/r/laralordproject/server](https://hub.docker.com/r/laralordproject/server)

   ```
      $ docker run laralordproject/server:latest   
   ```

[Menu](../README.md#documentation) &nbsp; &nbsp;  [Top](#top)
