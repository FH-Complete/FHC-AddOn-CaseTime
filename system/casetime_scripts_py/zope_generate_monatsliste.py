# BER, 08.2015: Aufruf von Monatsliste generieren fuer SACHB und Monat, eventuell an Email versenden

import sys, traceback
import string, json, time, os
from Products.allow import MIMEText,MIMEBase,MIMEMultipart,Header,encode_base64

dbconn = context.script_getApplicationData('dbconn')
remote_ip=context.REQUEST.REMOTE_ADDR
remote_ip_str = """select count(*) from rechner where '%s' = ANY(fhcomplete_allowed_ip)""" % remote_ip
allowed_ip = context.sql_execute(dbconn, remote_ip_str)
if not allowed_ip[1][0][0]:
   return json.dumps({'STATUS':'ERR','RESULT':'Not Allowed'}, ensure_ascii=False, encoding='utf8')

ret_dict = { 'error': '', 'sachb': '', 'monat': '', 'email': '', 'message': '' }

try:
    if ps_sachb == '' or ps_monat == '':
        ret_dict['error'] = 'Der Request benötigt als Parameter ein SACHB Kürzel und das MONAT, für welches die Monatsliste erstellt werden soll. Optional kann eine EMAIL Adresse mitgegeben werden, an welche die erstellte Liste gesendet wird.'
    else:
        ls_dbconn = context.script_getApplicationData('dbconn')
        mailhost = context.mailhost
        ret_dict['sachb'] = ps_sachb.upper()
        ret_dict['monat'] = ps_monat

        # Einstellungen fuer das Erzeugen des Datastores
        p_window = 'zrep_monatsliste'
        updtable = 'SACHBEARBEITER'
        updfields = ['SACHB:0', 'NAME:1', 'VORNAME:2', 'PERSNR:3', 'ABTEILUNG:4', 'FIRMENNR:5', 'VERTRAGSTYP:6' ]
        fieldlist = ['SACHB', 'NAME', 'VORNAME', 'PERSNR', 'ABTEILUNG', 'FIRMENNR', 'VERTRAGSTYP']
        arguments = []
        upd_pkfields = 'SACHB'

        sql = """select SACHB, NAME, VORNAME, PERSNR, ABTEILUNG, FIRMENNR, VERTRAGSTYP
                 from   SACHBEARBEITER
                 where  coalesce(GELOESCHT,'N') = 'N'
                 and    coalesce(NICHTSTEMPLER,'N') = 'N'
                 and    (cast('01.'||to_char(EINTRITTSDATUM,'mm.yyyy') as date) <= cast('01.%s' as date) or EINTRITTSDATUM is null)
                 and    SACHB = upper('%s')""" %(ps_monat, ps_sachb)

        # Existiert der Datastore bereits, wenn nicht neu anlegen ?
        tempfolder = context.restrictedTraverse('zdw_datastores')
        sessionid = context.restrictedTraverse('/dojoKernel').get_sessionid()
        ds = 'ds_' + sessionid + '_' + str(p_window)
        if hasattr(tempfolder,ds) == 0:
            # Datastore im TEMP Folder anlegen
            tempfolder.manage_addProduct['Datastore'].addDatastore(ds, '')

        instance = getattr(tempfolder,ds)
        instance.initialize(ds, ls_dbconn, updtable, updfields, sql, fieldlist, arguments, upd_pkfields)
        instance.retrieve([])
        #context.script_log('/sync/generate_monatsliste.py Datastore Instanz: ' , str(instance.senddatastore()))

        if instance.countrows() <= 0:
            ret_dict['error'] = 'Es konnte keine Monatsliste für ' + ps_sachb.upper() + ' - ' + ps_monat + ' erstellt werden, da keine Daten vorhanden sind!'
        if instance.countrows() > 0:
            # Welche Monatsliste und Absender aus Rechner holen
            sql_ext = "select EXTERNAL_MONATSLISTE, coalesce(VERANTWORTLICHE, 'office@case.at') from RECHNER"
            ret_ext = context.sql_execute(ls_dbconn, sql_ext)
            errors = ret_ext[0]
            data = ret_ext[1]
            if len(errors) > 0:
                context.script_log('FEHLER 01 in /sync/generate_monatsliste.py: ', str(errors))
                ret_dict['error'] = 'FEHLER 01 in /sync/generate_monatsliste.py: ' + str(errors)
            elif len(data) == 0:
                # Keine Daten in der DB vorhanden
                context.script_log('FEHLER 02 in /sync/generate_monatsliste.py: ', 'Keine External für die Monatsliste in der Tabelle Rechner eingetragen')
                ret_dict['error'] = 'FEHLER 02 in /sync/generate_monatsliste.py: Keine External für die Monatsliste  in der Tabelle Rechner eingetragen'
            for elm in data:
                ls_external = str(elm[0])
                ls_absender = str(elm[1])
            #context.script_log('/sync/generate_monatsliste.py External - Absender: ' , ls_external + ' --- ' + ls_absender)

            # Einstellungen für das Drucken der Monatsliste
            pPathToSave = '/casetime/CASE/docs/'
            pSysPathToSave = context.GetEnviron('INSTANCE_HOME') + '/var/vars/documents/'
            pFilename = ps_sachb.upper() + '/' + str(p_window)
            pfad = '/casetime/CASE/Datawindows/zrep_monatsliste/' + ls_external
            external =  context.restrictedTraverse(pfad)
            #context.script_log('/sync/generate_monatsliste.py Path - SysPath - File - Pfad - External: ' , pPathToSave + ' --- ' + pSysPathToSave + ' --- ' + pFilename + ' --- ' + pfad + ' --- ' + str(external))

            # Liste drucken; es kommt der modifizierte Filename zurueck
            lb_auto = True
            retFile = str(external(ds, pFilename, ftype, pPathToSave, sessionid, ps_monat, ls_dbconn, auto=True))
            #context.script_log('/sync/generate_monatsliste.py retfile: ' , retFile)
            ret_dict['webfile'] = pPathToSave + retFile
            ret_dict['sysfile'] = pSysPathToSave + retFile
            ret_dict['message'] = 'Die Monatsliste wurde für ' + ps_sachb.upper() + ' - ' + ps_monat + ' erstellt!'

            if ls_absender <> '' and ps_email <> '' and mailhost:
                # Versenden der Monatsliste an die Email Adresse
                # Text zur Mail hinzufügen
                betr = 'Monatsliste für ' + ps_sachb.upper() + ' - ' + ps_monat
                mailContent = 'Monatsliste für ' + ps_sachb.upper() + ' - ' + ps_monat + '\n\nDiese befindet sich im Anhang. \n\nMit freundlichen Grüßen \nIhr caseTIME - Team'
                msg = MIMEMultipart()
                inner = MIMEText(mailContent,_charset='utf-8')
                msg.attach(inner)
                msg.add_header('Subject', str(Header(betr,"utf-8")))
                # Anhang zur Mail hinzufügen
                obj = context.restrictedTraverse(pPathToSave + retFile)
                mt,st = obj.content_type.split("/")
                p = MIMEBase(mt,st)
                p.set_payload(str(obj))
                p.add_header('content-disposition', 'attachment', filename=obj.getId())
                encode_base64(p)
                msg.attach(p)
                # Email senden
                ls_absender = str('Zeitaufzeichnung <noreply@technikum-wien.at>')
                mailhost.send(msg.as_string(), ps_email, ls_absender, betr)
                ret_dict['email'] = ps_email
                ret_dict['message'] = ret_dict['message'][:-1] + ' und an die Emailadresse ' + ps_email + ' versendet!'

    return json.dumps(ret_dict)
except:
    # Ende mit Fehler.
    tb = sys.exc_info()[2]
    info = str(sys.exc_info()[0]) + str(traceback.format_tb(tb))
    context.script_log('/sync/generate_monatsliste.py.1, Fehler: ', info)
    ret_dict['error'] = info
    return json.dumps(ret_dict)
