#!/bin/bash
rm -rf PHPSchemaManager
git clone -b development https://github.com/thiagomp/PHPSchemaManager.git PHPSchemaManager
php PHPSchemaManager/autotest/psmbuild.php $1