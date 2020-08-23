%define name recodex-core
%define short_name api
%define install_dir /opt/%{name}
%define version 1.22.0
%define unmangled_version 19fb2b15004261ddf7bafd30d1a92eb22e0375f4
%define release 14

Summary: ReCodEx core API component
Name: %{name}
Version: %{version}
Release: %{release}
License: MIT
Group: Development/Libraries
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-buildroot
Prefix: %{_prefix}
Vendor: Petr Stefan <UNKNOWN>
Url: https://github.com/ReCodEx/api
Requires(post): systemd
Requires(preun): systemd
Requires(postun): systemd
Requires: git php php-json php-mysqlnd php-ldap php-pecl-yaml php-pecl-zip php-pecl-zmq php-xml php-intl php-mbstring

Source0: https://github.com/ReCodEx/%{short_name}/archive/%{unmangled_version}.tar.gz#/%{short_name}-%{unmangled_version}.tar.gz

%global debug_package %{nil}

%description
Core business logic and REST API of ReCodEx code examiner, an educational application for evaluating programming assignments.

%prep
%setup -n %{short_name}-%{unmangled_version}
curl "https://getcomposer.org/composer-stable.phar" -o composer-stable.phar

%build
# Nothing to do here

%install
mkdir -p %{buildroot}%{install_dir}
mkdir -p %{buildroot}/var/log/recodex/core-api
ln -sf /var/log/recodex/core-api %{buildroot}%{install_dir}/log
mkdir -p %{buildroot}%{install_dir}/job_config
mkdir -p %{buildroot}%{install_dir}/uploaded_data
mkdir -p %{buildroot}%{install_dir}/temp
cp -r www %{buildroot}%{install_dir}/www
cp -r app %{buildroot}%{install_dir}/app
cp -r migrations %{buildroot}%{install_dir}/migrations
cp composer.json composer.lock composer-stable.phar cleaner %{buildroot}%{install_dir}/
mkdir -p %{buildroot}/%{_sysconfdir}/recodex/core-api
mv %{buildroot}%{install_dir}/app/config/config.local.neon.example %{buildroot}%{install_dir}/app/config/config.local.neon
ln -sf %{install_dir}/app/config/config.local.neon %{buildroot}/%{_sysconfdir}/recodex/core-api/config.local.neon

%clean


%post
setfacl -Rd -m u:apache:rwX %{install_dir}/temp
setfacl -Rd -m u:recodex:rwX %{install_dir}/temp
setfacl -Rd -m u:apache:rwX %{install_dir}/job_config
setfacl -Rd -m u:recodex:rwX %{install_dir}/job_config
setfacl -Rd -m u:apache:rwX %{install_dir}/uploaded_data
setfacl -Rd -m u:recodex:rwX %{install_dir}/uploaded_data
setfacl -Rd -m u:apache:rwX /var/log/recodex/core-api
setfacl -Rd -m u:recodex:rwX /var/log/recodex/core-api

# Install dependencies
php %{install_dir}/composer-stable.phar install --no-ansi --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader --working-dir=%{install_dir}/

# Run cleaner after installation
%{install_dir}/cleaner



%postun

%pre
getent group recodex >/dev/null || groupadd -r recodex
getent passwd recodex >/dev/null || useradd -r -g recodex -d %{_sysconfdir}/recodex -s /sbin/nologin -c "ReCodEx Code Examiner" recodex
exit 0

%preun
if [ $1 == 0 ]; then
	%{install_dir}/cleaner
	rm -rf %{install_dir}/vendor
	rm -rf %{install_dir}/temp
fi

%files
%defattr(-,recodex,recodex)
%dir %{install_dir}
%dir %attr(-,recodex,recodex) %{_sysconfdir}/recodex/core-api
%dir %attr(0775,apache,recodex) /var/log/recodex/core-api
%attr(0775,apache,recodex) %{install_dir}/log
%dir %attr(0775,apache,recodex) %{install_dir}/job_config
%dir %attr(0775,apache,recodex) %{install_dir}/uploaded_data
%dir %attr(0775,apache,recodex) %{install_dir}/temp
%dir %{install_dir}/app

%{install_dir}/app/bootstrap.php
%{install_dir}/app/commands
%{install_dir}/app/exceptions
%{install_dir}/app/helpers
%{install_dir}/app/http
%{install_dir}/app/model
%{install_dir}/app/presenters
%{install_dir}/app/router
%{install_dir}/app/V1Module
%{install_dir}/app/web.config
%{install_dir}/app/.htaccess
%{install_dir}/composer.json
%{install_dir}/composer.lock
%{install_dir}/composer-stable.phar
%attr(0770,recodex,recodex) %{install_dir}/cleaner
%{install_dir}/migrations
%{install_dir}/www
%attr(0660,apache,recodex) %{_sysconfdir}/recodex/core-api/config.local.neon

%config %{install_dir}/app/config/config.neon
%config %{install_dir}/app/config/permissions.neon
%config(noreplace) %attr(0660,apache,recodex) %{install_dir}/app/config/config.local.neon

%changelog

