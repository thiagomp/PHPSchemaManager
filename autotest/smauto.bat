@echo off
rd /s /q PHPSchemaManager
git clone -b development https://github.com/thiagomp/PHPSchemaManager.git PHPSchemaManager
php PHPSchemaManager/autotest/psmbuild.php %1%