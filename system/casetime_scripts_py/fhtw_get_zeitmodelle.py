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

def get_zeitmodelle(self):
    
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



    # datum und faktor der feiertage für den sachb  holen für anz_tage bis datum
    sql_str = """select zeitmodell, bezeichnung, sollmo, solldi, sollmi, solldo, sollfr, sollsa, sollso, sollmo+solldi+sollmi+solldo+sollfr+sollsa+sollso as summe from zeitmodelle order by summe desc, zeitmodell asc;
    """

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



