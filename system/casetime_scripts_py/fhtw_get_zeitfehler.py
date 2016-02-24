#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys, traceback
import string
import json

from Products.PythonScripts.standard import html_quote
#request = container.REQUEST

#response =  request.response

def get_zeitfehler(self, sachb):
    
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


    #sql_check = """select * from sachbearbeiter where sachb = '%s'""" % foo
    sql_str ="""select distinct Z.BUCHUNGSDATUM as DATUM,
                        Z.VERARBFEHLER
                    from   ZEITROHDATEN Z, SACHBEARBEITER S
                    where  Z.SACHB = S.SACHB
                    and    Z.VERARBFEHLER is not null
                    and    coalesce(Z.FEHLEROK,'N') = 'N'
                    and    Z.BWART not in ('AA','AE')
                    and S.SACHB = '%(sachb)s' 
                union select distinct F.DATUM as DATUM,
                        F.VERARBFEHLER
                   from   ZEITFEHLER F, SACHBEARBEITER B
                   where  F.SACHB = B.SACHB
                   and B.SACHB = '%(sachb)s'
                   and    F.VERARBFEHLER is not null
                   and    coalesce(F.FEHLEROK,'N') = 'N'; 
            """ % vars_dict


    #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

    erg = self.sql_execute(dbconn,sql_str)
    
    error = erg[0]
    data = erg[1]
    if len(error) > 0:
        ret_dict['STATUS']='ERR'
        ret_dict['RESULT'] = str(error[0])
    else:
        ret_dict['STATUS']='OK'
        ret_dict['RESULT'] = data
    return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')

    #print erg[1]



