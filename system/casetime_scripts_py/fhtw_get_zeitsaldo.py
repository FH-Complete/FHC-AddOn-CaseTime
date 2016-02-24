#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys, traceback
import string
import json
import time

from Products.PythonScripts.standard import html_quote
#request = container.REQUEST

#response =  request.response

# returns zeitsaldo für sachb in stunden

def get_zeitsaldo(self, sachb):
    
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


    vars_dict = {}
    vars_dict['sachb'] = sachb.upper()
    vars_dict['datum'] = time.strftime("%d.%m.%Y")

    # dbconn = self.script_getApplicationData('dbconn')
    #context.script_log('/sync/rohdaten_import DBConn:', str(dbconn))

    #sql_check = """select * from sachbearbeiter where sachb = '%s'""" % foo
    sql_str_check = """select count(*) from flex_zeitmodelle where sachb = '%(sachb)s'""" % vars_dict
    erg_check = self.sql_execute(dbconn,sql_str_check) 
    if erg_check[1][0][0] == 0:
        ret_dict['STATUS']='ERR'
        ret_dict['RESULT'] = 'Zeitmodell fehlt!'
        return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
    else:
        sql_str ="""select cast(GETSALDOEXKLZUKUNFT('%(sachb)s','%(datum)s','SAL') as numeric);
                """ % vars_dict


        #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

        erg = self.sql_execute(dbconn,sql_str)
        
        error = erg[0]
        data = erg[1][0][0]
        if len(error) > 0:
            ret_dict['STATUS']='ERR'
            ret_dict['RESULT'] = str(error[0])
        else:
            ret_dict['STATUS']='OK'
            ret_dict['RESULT'] = data
        return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')

    #print erg[1]



