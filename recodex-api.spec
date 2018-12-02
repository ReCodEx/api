%define name recodex-api
%define short_name api
%define version 1.13.0
%define unmangled_version cd4ae43bba0d4d29b196e2a7bfb622bcded794cb
%define release 1

Summary: ReCodEx API component
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
Requires: composer php php-fpm php-json php-mysqlnd php-ldap php-pecl-zip php-pecl-zmq php-xml

#Source0: %{name}-%{unmangled_version}.tar.gz
Source0: https://github.com/ReCodEx/%{short_name}/archive/%{unmangled_version}.tar.gz#/%{short_name}-%{unmangled_version}.tar.gz

%global debug_package %{nil}

%description
REST API of ReCodEx programmer testing solution.

%prep
%setup -n %{short_name}-%{unmangled_version}

%build
# Nothing to do here

%install
mkdir -p %{buildroot}/opt/recodex-api
mkdir -p %{buildroot}/opt/recodex-api/log
mkdir -p %{buildroot}/opt/recodex-api/job_config
mkdir -p %{buildroot}/opt/recodex-api/uploaded_data
mkdir -p %{buildroot}/opt/recodex-api/temp
cp -r www %{buildroot}/opt/recodex-api/www
cp -r app %{buildroot}/opt/recodex-api/app
cp -r migrations %{buildroot}/opt/recodex-api/migrations
cp composer.json composer.lock cleaner %{buildroot}/opt/recodex-api/
mv %{buildroot}/opt/recodex-api/app/config/config.local.neon.example %{buildroot}/opt/recodex-api/app/config/config.local.neon

%clean


%post
# Install dependencies
composer install --no-ansi --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader

# Run cleaner after installation
/opt/recodex-api/cleaner

# Run migrations
php /opt/recodex-api/www/index.php migrations:migrate

# Run cleaner again
/opt/recodex-api/cleaner

%systemd_post 'php-fpm.service'

%postun
%systemd_postun_with_restart 'php-fpm.service'

%pre
getent group recodex >/dev/null || groupadd -r recodex
getent passwd recodex >/dev/null || useradd -r -g recodex -d %{_sysconfdir}/recodex -s /sbin/nologin -c "ReCodEx Code Examiner" recodex
exit 0

%preun
%systemd_preun 'php-fpm.service'

%files
%defattr(-,recodex,recodex)
%dir /opt/recodex-api/log
%dir /opt/recodex-api/job_config
%dir /opt/recodex-api/uploaded_data
%dir /opt/recodex-api/temp

/opt/recodex-api/migrations/*
/opt/recodex-api/www/*
/opt/recodex-api/app/bootstrap.php
/opt/recodex-api/app/commands/*
/opt/recodex-api/app/exceptions/*
/opt/recodex-api/app/helpers/*
/opt/recodex-api/app/http/*
/opt/recodex-api/app/model/*
/opt/recodex-api/app/presenters/*
/opt/recodex-api/app/router/*
/opt/recodex-api/app/V1Module/*
/opt/recodex-api/app/web.config
/opt/recodex-api/composer.json
/opt/recodex-api/composer.lock
%attr(0755,recodex,recodex) /opt/recodex-api/cleaner
/opt/recodex-api/app/.htaccess
/opt/recodex-api/www/.htaccess
/opt/recodex-api/www/.maintenance.php

%config /opt/recodex-api/app/config/config.neon
%config /opt/recodex-api/app/config/permissions.neon
%config(noreplace) /opt/recodex-api/app/config/config.local.neon

%changelog

