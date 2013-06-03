# PHPSchemaManager auto test scripts

The idea is to place here all scripts used to automatically test the library.  
As of now, I'm using Linux to execute the automated tests, check the script below:

```bash
#!/bin/bash
rm -rf PHPSchemaManager
git clone -b development https://github.com/thiagomp/PHPSchemaManager.git PHPSchemaManager
php PHPSchemaManager/autotest/psmbuild.php
```

As you can see, it's very simple.  
It removes the library directory, download the code from the library directly from github and then call the PHP script created to execute the tests.  

The idea is to have a tool like Jenkins to execute this script from time to time and keep the history of the execution.  
Usually, this kind of tool captures everything that is printed in the screen, that's why I'm not worrying to build a log solution for this automated test.