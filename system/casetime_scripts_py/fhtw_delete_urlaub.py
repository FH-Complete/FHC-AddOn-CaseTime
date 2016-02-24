#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys, traceback
import string
import json

from Products.PythonScripts.standard import html_quote
#request = container.REQUEST

#response =  request.response

def delete_urlaub(self):
    
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
    sachb = self.REQUEST.sachb
    lohnart = 'UB'
    auftragsnummer = '1'
    buchdat = self.REQUEST.buchdat
    drucken = 'J'
    auftragsposition = '1'
    stunden = 1


    vars_dict = {}
    vars_dict['sachb'] = sachb.upper()
    vars_dict['lohnart'] = lohnart
    vars_dict['auftragsnummer'] = auftragsnummer
    vars_dict['buchdat'] = buchdat
    vars_dict['drucken'] = drucken
    vars_dict['auftragsposition'] = auftragsposition
    vars_dict['stunden'] = stunden


    #sql_check = """select * from sachbearbeiter where sachb = '%s'""" % foo
    sql_str = """ delete from ma_zeit where sachb = '%(sachb)s' 
                    and lohnart = 'UB' and
                    auftragsnummer = '1' and
                    buchdat = '%(buchdat)s'
                    """ % vars_dict

    #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

    erg = self.sql_execute(dbconn,sql_str)
    error = erg[0]
    if len(error) > 0:
        ret_dict['STATUS']='ERR'
        ret_dict['RESULT'] = str(error[0])
    else:
        ret_dict['STATUS']='OK'
        ret_dict['RESULT'] = 'Urlaubseintrag gelöscht'
    return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')

    #print erg[1]



