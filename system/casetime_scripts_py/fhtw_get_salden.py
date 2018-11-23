#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys, traceback
import string
import json
import time
from datetime import date

from Products.PythonScripts.standard import html_quote

# returns salden (Zeitsaldo, Urlaubsanspruch und Urlaubsstand) für die per POST übergebenen SACHBs:
# {"STATUS": "OK", "RESULT": {"UID": {"UrlaubAktuell": 99, "UrlaubAnspruch": 25.0, "Zeitsaldo": 1},...}}

def get_salden(self):

    dbconn = self.script_getApplicationData('dbconn')
    ### check if request is from authorized ip
    remote_ip=self.REQUEST.REMOTE_ADDR
    remote_ip_str = """select count(*) from rechner where '%s' = ANY(fhcomplete_allowed_ip)""" % remote_ip
    allowed_ip = self.sql_execute(dbconn, remote_ip_str)
    if not allowed_ip[1][0][0]:
        return json.dumps({'STATUS':'ERR','RESULT':'Not Allowed'}, ensure_ascii=False, encoding='utf8')
    ### end check

    ret_dict = {'STATUS':'', 'RESULT':{}}

    # UIDs aus dem Post-Request holen
    userarr = self.REQUEST.form
    sachbs = userarr.values()

    for s in sachbs:
        # Zeitsaldo holen
        data_dict = {}
        vars_dict = {}

        jahr = date.today().year
        monat = date.today().month
        if monat < 9:
            jahr = jahr - 1
        vars_dict['jahr'] = jahr
        vars_dict['sachb'] = s
        vars_dict['datum'] = time.strftime("%d.%m.%Y")

        sql_str_check = """select count(*) from flex_zeitmodelle where sachb = '%(sachb)s'""" % vars_dict
        erg_check = self.sql_execute(dbconn,sql_str_check)
        if erg_check[1][0][0] == 0:
            pass
        else:
            sql_str ="""select cast(GETSALDOEXKLZUKUNFT('%(sachb)s','%(datum)s','SAL') as numeric);
                    """ % vars_dict

            erg = self.sql_execute(dbconn,sql_str)

            error = erg[0]
            data = erg[1][0][0]
            if len(error) > 0:
                pass
            else:
                data_dict['Zeitsaldo'] = data

            # Urlaubssalden holen
            # Saldo inkl zukunft holen
            sql_str ="""select cast(GETSALDO('%(sachb)s','%(datum)s','URL') as numeric);
                    """ % vars_dict

            erg = self.sql_execute(dbconn,sql_str)

            error = erg[0]
            saldo = erg[1][0][0]
            data_dict["UrlaubAktuell"] = saldo

            # Anspruch holen
            sql_str = """select sum(stunden) from ma_zeit where sachb='%(sachb)s' and lohnart = 'UZ' and buchdat >= '%(jahr)s-09-01'
            """ % vars_dict

            erg = self.sql_execute(dbconn,sql_str)

            error = erg[0]
            urlaubstage = erg[1][0][0]
            if not urlaubstage:
                urlaubstage = 0
            data_dict["UrlaubAnspruch"] = urlaubstage


        ret_dict['STATUS']='OK'
        ret_dict['RESULT'][vars_dict['sachb']] = data_dict
    return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
