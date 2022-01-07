#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys, traceback
import string
import json

from Products.PythonScripts.standard import html_quote
#request = container.REQUEST

#response =  request.response

def import_zeitsperre(self, sachb, buchdat, art, zeit):

    dbconn = self.script_getApplicationData('dbconn')
    ### check if request is from authorized ip
    remote_ip=self.REQUEST.REMOTE_ADDR
    remote_ip_str = """select count(*) from rechner where '%s' = ANY(fhcomplete_allowed_ip_sync)""" % remote_ip
    allowed_ip = self.sql_execute(dbconn, remote_ip_str)
    if not allowed_ip[1][0][0]:
        return json.dumps({'STATUS':'ERR','RESULT':'Not Allowed'}, ensure_ascii=False, encoding='utf8')
    ### end check

    # dict für jason
    ret_dict = {'STATUS':'', 'RESULT':''}

    if art == 'urlaub':
        lohnart = 'UB'
        auftragsnummer = '1'
        drucken = 'J'
        auftragsposition = '1'
        stunden = 1
    elif art == 'krankenstand':
        lohnart = 'K'
        auftragsnummer = '1'
        drucken = 'J'
        auftragsposition = '5'
        stunden = 0
    elif art == 'zeitausgleich':
        lohnart = 'ZA'
        auftragsnummer = '1'
        drucken = 'J'
        auftragsposition = '6'
        stunden = 0
    elif art == 'pflegeurlaub':
        lohnart = 'UP'
        auftragsnummer = '1'
        drucken = 'J'
        auftragsposition = '11'
        stunden = 0
    elif art == 'dienstverhinderung':
        lohnart = 'AB'
        auftragsnummer = '1'
        drucken = 'J'
        auftragsposition = '3'
        stunden = 0
    elif art == 'externelehre':
        lohnart = 'EL'
        auftragsnummer = '1'
        drucken = 'J'
        auftragsposition = '61'
        stunden = zeit
    elif art == 'ersatzruhe':
        lohnart = 'ER'
        auftragsnummer = '1'
        drucken = 'J'
        auftragsposition = '62'
        stunden = zeit
    elif art == 'krankenstandcovid':
        lohnart = 'KC'
        auftragsnummer = '1'
        drucken = 'J'
        auftragsposition = '5'
        stunden = 0

    else:
        ret_dict['STATUS']='ERR'
        ret_dict['RESULT'] = str('lohnart nicht unterstützt')
        return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')



    vars_dict = {}
    vars_dict['sachb'] = sachb.upper()
    vars_dict['lohnart'] = lohnart
    vars_dict['auftragsnummer'] = auftragsnummer
    vars_dict['buchdat'] = buchdat
    vars_dict['drucken'] = drucken
    vars_dict['auftragsposition'] = auftragsposition
    vars_dict['stunden'] = stunden


    sql_str_std = """select cast(GETSOLLSTUNDEN('%(sachb)s', '%(buchdat)s', 'J') as numeric)""" % vars_dict
    std = self.sql_execute(dbconn,sql_str_std)
    if (std[1][0][0] >= 0 and (art == 'krankenstand' or art == 'zeitausgleich' or art == 'pflegeurlaub' or art == 'dienstverhinderung' or art == 'krankenstandcovid')):
        vars_dict['stunden'] = std[1][0][0]

    #sql_check = """select * from sachbearbeiter where sachb = '%s'""" % foo
    sql_str =""" insert into ma_zeit (sachb, lohnart, auftragsnummer, buchdat, drucken, auftragsposition, stunden)
                values ('%(sachb)s', '%(lohnart)s', '%(auftragsnummer)s', '%(buchdat)s', '%(drucken)s', '%(auftragsposition)s', '%(stunden)s')
                """ % vars_dict


    #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

    erg = self.sql_execute(dbconn,sql_str)
    error = erg[0]
    if len(error) > 0:
        ret_dict['STATUS']='ERR'
        ret_dict['RESULT'] = str(error[0])
    else:
        ret_dict['STATUS']='OK'
        ret_dict['RESULT'] = 'Zeitsperreneintrag erfolgreich'
    return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')

    #print erg[1]
