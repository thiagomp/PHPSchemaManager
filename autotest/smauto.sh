#!/bin/bash
rm -rf PHPSchemaManager
git clone -b $1 https://github.com/thiagomp/PHPSchemaManager.git PHPSchemaManager
php PHPSchemaManager/autotest/psmbuild.php $1