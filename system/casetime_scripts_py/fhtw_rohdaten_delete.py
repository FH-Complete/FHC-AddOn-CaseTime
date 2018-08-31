#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys, traceback
import string
import json

from Products.PythonScripts.standard import html_quote


def rohdaten_delete(self):

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
    datum = self.REQUEST.datum
    datum_bis = self.REQUEST.datum_bis
    typ = self.REQUEST.typ


    vars_dict = {}
    vars_dict['sachb'] = sachb.upper()
    vars_dict['datum'] = datum
    if typ == 'da':
        vars_dict['datum_bis'] = datum_bis
        sql_str = """ delete from zeitrohdaten where sachb = '%(sachb)s' and
            datum in ('%(datum)s','%(datum_bis)s') and bwart in ('DA', 'DE')
            """ % vars_dict
    else:
        sql_str = """ delete from zeitrohdaten where sachb = '%(sachb)s' and
                datum = '%(datum)s' and bwart not in ('DA', 'DE')
                """ % vars_dict

    erg = self.sql_execute(dbconn,sql_str)
    error = erg[0]
    if len(error) > 0:
        ret_dict['STATUS']='ERR'
        ret_dict['RESULT'] = str(error[0])
    else:
        ret_dict['STATUS']='OK'
        ret_dict['RESULT'] = 'Einträge erfolgreich gelöscht'
    return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
