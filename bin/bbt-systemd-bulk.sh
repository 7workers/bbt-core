#!/bin/bash

RED='\033[0;31m\033[1m'
NC='\033[0m' # No Color
HR=`printf -- "=%.0s" $(seq $(tput cols))`

check_units()
{
  local services=($1)

  out_all="UNIT|STATUS\n"

  for i in "${services[@]}"
  do
    serviceStatus=$(systemctl --user status "$i" 2>/dev/null | sed -n '3 p')

    if [ -z "${serviceStatus}" ]
    then
      serviceStatus="${RED} SERVICE NOT RUNNING${NC}"
    fi

    if echo "${serviceStatus}" | grep --quiet 'failed\|dead';
      then
      serviceStatus="${RED}${serviceStatus}${NC}"
    fi

  out_all="${out_all}\n${i}|${serviceStatus}"
  done

  echo -e ${out_all} | column -s "|" -t
}

case $1 in
    start|stop|restart|enable|disable|status|check|list) ;;
    *) echo "start ? stop ? restart ? enable? disable ? status ? check? list?"; exit 1 ;;
esac

DIR_UNIT_FILES=$2
services=(`ls ${DIR_UNIT_FILES}*.service | xargs -n 1 basename`)

allServices=()

for item in "${services[@]}"; do
  testTimer="${item%%.service}.timer"
  if test -f "${DIR_UNIT_FILES}/${testTimer}";
  then
    allServices+=("$testTimer")
  else
    if [[ $item =~ "@.service" ]] ;
    then
      testMultiService="${item%%@.service}*.service"
      arInstalledUnits=(`systemctl --user list-units --all "${testMultiService}" | tail -n +2 | head -n -6 | grep -oP "@\d+\."`)
      if ((${#arInstalledUnits[@]})); then
        for unitInstance in "${arInstalledUnits[@]}"; do
          singleServiceInMulti="${item%%@.service}${unitInstance}service"
          allServices+=("$singleServiceInMulti")
        done
      else
        singleServiceInMulti="${item%%@.service}@1.service"
        allServices+=("$singleServiceInMulti")
      fi
    else
      if [[ -n "${item}" ]]; then
        allServices+=("$item")
      fi
    fi
  fi
done

servicesAsString="${allServices[*]}"

case $1 in
    start|stop|restart|enable|disable)
      echo -e "${RED}EXECUTING:${NC} systemctl --user $1 ${servicesAsString}"
      systemctl --user $1 $servicesAsString
      echo ""
      ;;
    list)
      echo "${HR}"
      echo "${servicesAsString}"
      echo "${HR}"
      ;;
    check)
      check_units "${servicesAsString}"
      ;;
    *)
    ;;
esac

exit

