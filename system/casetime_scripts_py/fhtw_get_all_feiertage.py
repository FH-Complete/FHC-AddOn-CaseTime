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

def get_all_feiertage(self):

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

    try:
        datum_von = time.strptime(datum, "%Y%m%d")
    except:
        return 'Falsches Datumsformat'
    vars_dict['datum'] = datum
    vars_dict['anz_tage'] = anz_tage

    # datum und faktor der feiertage für den sachb  holen für anz_tage bis datum
    sql_str ="""select distinct f.datum, f.faktor from feiertage f WHERE fkid=1
            """

    erg = self.sql_execute(dbconn,sql_str)

    error = erg[0]
    data = erg[1]
    data_flat = []
    for d in data:
        data_flat.append(d[0])
    if len(error) > 0:
        ret_dict['STATUS']='ERR'
        ret_dict['RESULT'] = str(error[0])
    else:
        ret_dict['STATUS']='OK'
        ret_dict['RESULT'] = data
    return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')


    #print erg[1]
