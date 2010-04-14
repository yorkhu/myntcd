#                                               -*- Autoconf -*-
# Process this file with autoconf to produce a configure script.
AC_PREREQ(2.59)
AC_INIT(myntcd, 0.1, pinter@georgikon.hu)
AM_INIT_AUTOMAKE(myntcd, 0.1)
AC_CONFIG_SRCDIR([Makefile.am])
AM_CONFIG_HEADER(config.h)

# Checks for programs.
AC_PROG_CC

# Checks for libraries.
AC_CHECK_LIB([pcap], [pcap_open_live], [LIBS="$LIBS -lpcap"], [AC_MSG_ERROR( could not find pcap library)])
AC_CHECK_LIB([pthread], [pthread_create], [LIBS="$LIBS -lpthread"], [AC_MSG_ERROR(could not find pthread library)])

# Checks for header files.
AC_HEADER_STDC
AC_HEADER_SYS_WAIT
AC_CHECK_HEADERS([stdlib.h string.h syslog.h unistd.h])

# Checks for typedefs, structures, and compiler characteristics.
AC_C_CONST
AC_TYPE_PID_T
AC_C_VOLATILE

# Checks for library functions.
AC_FUNC_FORK
AC_FUNC_MALLOC
AC_TYPE_SIGNAL
AC_FUNC_STRFTIME
AC_CHECK_FUNCS([alarm memmove strcasecmp strchr strcspn strdup strtol])
AC_CONFIG_FILES([Makefile
		 src/Makefile])
AC_OUTPUT
