#!/bin/sh

yum-builddep -y recodex-api.spec
spectool -g recodex-api.spec
cp api-*.tar.gz ~/rpmbuild/SOURCES/
rpmbuild -ba recodex-api.spec

