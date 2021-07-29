#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys, traceback
import string
import json
import time
from datetime import date

from Products.PythonScripts.standard import html_quote
#request = container.REQUEST

#response =  request.response

def get_urlaubsaldo(self, sachb):

    dbconn = self.script_getApplicationData('dbconn')
    ### check if request is from authorized ip
    remote_ip=self.REQUEST.REMOTE_ADDR
    remote_ip_str = """select count(*) from rechner where '%s' = ANY(fhcomplete_allowed_ip)""" % remote_ip
    allowed_ip = self.sql_execute(dbconn, remote_ip_str)
    if not allowed_ip[1][0][0]:
        return json.dumps({'STATUS':'ERR','RESULT':'Not Allowed'}, ensure_ascii=False, encoding='utf8') 
    ### end check

   
    # dict für jason
    ret_dict = {'STATUS':'', 'RESULT':''}
    data_dict = {}
    vars_dict = {}
    
    jahr = date.today().year
    monat = date.today().month
    if monat < 9:
        jahr = jahr - 1
    vars_dict['jahr'] = jahr
    vars_dict['sachb'] = sachb.upper()
    vars_dict['datum'] = time.strftime("%d.%m.%Y")


    # Urlaubssaldo inkl zukunft holen
    sql_str ="""select cast(GETSALDO('%(sachb)s','%(datum)s','URL') as numeric);
            """ % vars_dict


    #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

    erg = self.sql_execute(dbconn,sql_str)
    
    error = erg[0]
    saldo = erg[1][0][0]
    data_dict["AktuellerStand"] = saldo
    
    # Urlaubssaldo ohne zukunft vorjahr
    sql_str ="""select cast(GETSALDOEXKLZUKUNFT('%(sachb)s','%(jahr)s-08-31','URL') as numeric);
            """ % vars_dict


    #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

    erg = self.sql_execute(dbconn,sql_str)
    
    error = erg[0]
    saldo = erg[1][0][0]
    data_dict["Resturlaub"] = saldo
    
    # Urlaubsübertrag und Anspruch holen
    sql_str_old = """select urlaubstage, uzuebertrag from sachbearbeiter where sachb = '%(sachb)s'
            """ % vars_dict
    sql_str = """select sum(stunden) from ma_zeit where sachb='%(sachb)s' and lohnart = 'UZ' and buchdat >= '%(jahr)s-09-01'
    """ % vars_dict
    erg = self.sql_execute(dbconn,sql_str)
    error = erg[0]
    urlaubstage = erg[1][0][0]

    sql_str = """select sum(stunden) from ma_zeit where sachb='%(sachb)s' and lohnart = 'UZST' and buchdat >= '%(jahr)s-09-01'
        """ % vars_dict
    erg = self.sql_execute(dbconn,sql_str)
    error = erg[0]
    uzuebertrag = erg[1][0][0]

    sql_str = """select sum(stunden) from ma_zeit where sachb='%(sachb)s' and lohnart = 'UZSTN' and buchdat >= '%(jahr)s-09-01'
            """ % vars_dict
    erg = self.sql_execute(dbconn,sql_str)
    error = erg[0]
    uzuebertragNegativ = erg[1][0][0]
    if not urlaubstage:
        urlaubstage = 0
    if not uzuebertrag:
        uzuebertrag = 0
    if not uzuebertragNegativ:
        uzuebertragNegativ = 0

    data_dict["Urlaubsanspruch"] = urlaubstage
    data_dict["uzuebertrag"] = uzuebertrag
    data_dict["uzuebertragNegativ"] = uzuebertragNegativ
    if len(error) > 0:
        ret_dict['STATUS']='ERR'
        ret_dict['RESULT'] = str(error[0])
    else:
        ret_dict['STATUS']='OK'
        ret_dict['RESULT'] = data_dict
    return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')

    #print erg[1]



