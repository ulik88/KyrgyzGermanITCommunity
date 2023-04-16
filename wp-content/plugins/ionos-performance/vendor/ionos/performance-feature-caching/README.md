## Performance Caching Feature ##

This feature provides a caching mechanism for the ionos performance WordPress plugin.

### Testing ###

We wrote some tests using [playwright](https://playwright.dev), you can execute them using:
```bash
sh e2e-tests.sh
```
The script will create a docker container which is used for testing. The container will be stopped after test execution.