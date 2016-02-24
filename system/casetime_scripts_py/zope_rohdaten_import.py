import sys, traceback
import string
import json

#MUE 08.10.2014
#Schnittstelle für die Verarbeitung von z.B.:
#http://localhost:8080/sync/rohdaten_import?sachb=ber&bwart=KO&datumvon=20141001&zeitvon=071201&datumbis=20141001&zeitbis=163406
#Implementierung von Datumstest (sperrdatum < datumvon < datumbis, zeitvon < zeitbis wenn datumvon == datumbis)
#Rückgabe: JSON-Objekt: Dictionary["STATUS"]="OK" or "ERR", Dictionary["RESULT"]"TRANSNR_VON_TRANSNR_BIS" or "Fehlermeldung"
#Hinweis: Datensatzes werden mit TERMID='HTTP', POSNR=1, Hinweis='HTTP-Zeitbuchung', FEHLEROK='N', SACHBEARBEITER.VORNAME / NACHNAME (wenn vorhanden) hinzugefügt
#context.script_log('ROHDATEN_IMPORT.start ', str(sachb) + ' --- ' + str(bwart) + ' --- ' + str(datumvon)+ ' --- ' + str(zeitvon)+ ' --- ' + str(datumbis) + ' --- ' + str(zeitbis))

dbconn = context.script_getApplicationData('dbconn')
remote_ip=context.REQUEST.REMOTE_ADDR
#return remote_ip
remote_ip_str = """select count(*) from rechner where '%s' = ANY(fhcomplete_allowed_ip_sync)""" % remote_ip
allowed_ip = context.sql_execute(dbconn, remote_ip_str)
if not allowed_ip[1][0][0]:
   return json.dumps({'STATUS':'ERR','RESULT':'Not Allowed'}, ensure_ascii=False, encoding='utf8')

# Funktion zum Validieren der Zeit 000000,235959
def validate_mytime(t):
    if (0 <= int(t[0:2]) < 24) and (0 <= int(t[2:4]) < 60) and (0 <= int(t[4:6]) < 60):
       return 1
    return -1

# Funktion zum Validieren des Datums 00000101,99991231 mittels psql-cast
def validate_mydate(d, dbconn):
   try:
      sql = """select cast('%s' as date)""" %(str(d))
      
      erg = context.sql_execute(dbconn,sql)
      #context.script_log('/sync/rohdaten_import erg: ', str(erg))
      error = erg[0]
      data = erg[1]
      if len(error) > 0:
         return 'DATEVALIDATION ' + str(error[0])
      return 'OK'
   except:
      # Ende mit Fehler.
      val_dict = {'STATUS' : '', 'RESULT' : ''}
      tb = sys.exc_info()[2]
      info = str(sys.exc_info()[0]) + str(traceback.format_tb(tb))
      context.script_log('FEHLER in  /sync/rohdaten_import: ', str(info))

      val_dict['STATUS']='ERR'
      val_dict['RESULT']=str(info)
      
      return json.dumps(val_dict, ensure_ascii=False, encoding='utf8')
      
     
try:
   # Dictonary für JSON
   ret_dict = {'STATUS' : '', 'RESULT' : ''}
   
   # Überprüfung der Parameterliste
   if sachb == '' :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='Parameterliste fehlerhaft!(Sachbearbeiter fehlt)\nEXAMPLE: sachb=pam&bwart=KO&datumvon=20141007&zeitvon=070503&datumbis=20141007&zeitbis=163415' 
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   elif bwart == '' :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='Parameterliste fehlerhaft!(Bewegungsart fehlt)\nEXAMPLE: sachb=pam&bwart=KO&datumvon=20141007&zeitvon=070503&datumbis=20141007&zeitbis=163415'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   elif datumvon == '' :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='Parameterliste fehlerhaft!(Datum_von fehlt)\nEXAMPLE: sachb=pam&bwart=KO&datumvon=20141007&zeitvon=070503&datumbis=20141007&zeitbis=163415'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   elif zeitvon == '' :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='Parameterliste fehlerhaft!(Zeit_von fehlt)\nEXAMPLE: sachb=pam&bwart=KO&datumvon=20141007&zeitvon=070503&datumbis=20141007&zeitbis=163415'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   elif datumbis == '' :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='Parameterliste fehlerhaft!(Datum_bis fehlt)\nEXAMPLE: sachb=pam&bwart=KO&datumvon=20141007&zeitvon=070503&datumbis=20141007&zeitbis=163415'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   elif zeitbis == '' :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='Parameterliste fehlerhaft!(Zeit_bis fehlt)\nEXAMPLE: sachb=pam&bwart=KO&datumvon=20141007&zeitvon=070503&datumbis=20141007&zeitbis=163415'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')

   dbconn = context.script_getApplicationData('dbconn')
   context.script_log('/sync/rohdaten_import DBConn:', str(dbconn))
   
   #Speicherung der Transaktionsnummer
   transerg = ''
   #Variable für Sperrdatum
   sperrdatum = ''
   #Variable für die Prüfung ob Sachbearbeiter oder Bewegungsart vorhanden ist
   check_var = ''

   #Auslesen des Sperrdatums
   sql = "select to_char(sperrdatum, 'YYYYMMDD') from rechner"

   erg = context.sql_execute(dbconn,sql)
   #context.script_log('/sync/rohdaten_import erg: ', str(erg))
   error = erg[0]
   data = erg[1]
   if len(error) > 0:
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(error[0])
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   else:
       for line in data:
         sperrdatum+= str(line[0])

   #Fehlermeldungen bei Fehleingabe von Datum/Daten
   if datumvon > datumbis :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='DATUMVON ist älter als DATUMBIS!\nFormat: [YYYYMMDD]'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   elif validate_mytime(zeitvon) == -1 :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='ZEITVON ist außerhalb des Gültigkeitsbereichs'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   elif validate_mytime(zeitbis) == -1: 
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='ZEITBIS ist außerhalb des Gültigkeitsbereichs'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   elif zeitvon > zeitbis and datumvon == datumbis :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='ZEITVON ist älter als ZEITBIS!\nFormat: [HH24MISS]'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
      
   elif datumvon < sperrdatum :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='DATUMVON ist älter als das Sperrdatum!\nFormat: [YYYYMMDD]'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   
   # Prüfung ob Sachbearbeiter vorhanden ist
   sql_check = """select count(*)
                  from SACHBEARBEITER
                  where SACHB = UPPER('%s')""" %(sachb)

   #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

   erg = context.sql_execute(dbconn,sql_check)
   #context.script_log('/sync/rohdaten_import erg: ', str(erg))
   error = erg[0]
   data = erg[1]
   if len(error) > 0 :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(error[0])
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   else: 
      for line in data:
         check_var= str(line[0])
   if check_var == '0' :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='SACHB ist nicht vorhanden!'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   
   # Prüfung ob Bewegungsart vorhanden ist
   sql_check = """select count(*)
                  from BEWEGUNGSARTEN
                  where BWART = UPPER('%s')""" %(bwart)

   #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

   erg = context.sql_execute(dbconn,sql_check)
   #context.script_log('/sync/rohdaten_import erg: ', str(erg))
   error = erg[0]
   data = erg[1]
   if len(error) > 0 :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(error[0])
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   else: 
      for line in data:
         check_var= str(line[0])
   if check_var == '0' :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']='BWART ist nicht vorhanden!'
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   
   # Prüfung ob bereits ein Eintrag zu der Zeit mit diesem Sachbearbeiter vorhanden ist
   sql_check = """select count(*)
                  from ZEITROHDATEN
                  where SACHB = UPPER('%s')
                  and DATUM = '%s'
                  and ZEIT = '%s'""" %(sachb,datumvon,zeitvon)


   #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

   erg = context.sql_execute(dbconn,sql_check)
   #context.script_log('/sync/rohdaten_import erg: ', str(erg))
   error = erg[0]
   data = erg[1]
   if len(error) > 0 :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(error[0])
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   else: 
      for line in data:
         check_var= str(line[0])
   if str(check_var) == '1' :
      zeitvon = int(zeitvon)+1
      #ret_dict['STATUS']='ERR'
      #ret_dict['RESULT']="""SACHB %s hat bereits am %s zur Zeit %s einen Eintrag!""" %(sachb, datumvon, zeitvon)
      #return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   # NEU
   
   # Prüfung ob bereits ein Eintrag zu der Zeit mit diesem Sachbearbeiter vorhanden ist
   sql_check = """select count(*)
                  from ZEITROHDATEN
                  where SACHB = UPPER('%s')
                  and DATUM = '%s'
                  and ZEIT = '%s'""" %(sachb,datumbis,zeitbis)


   #context.script_log('/sync/rohdaten_import  SQL: ', str(sql_check))

   erg = context.sql_execute(dbconn,sql_check)
   #context.script_log('/sync/rohdaten_import erg: ', str(erg))
   error = erg[0]
   data = erg[1]
   if len(error) > 0 :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(error[0])
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   else: 
      for line in data:
         check_var= str(line[0])
   if check_var == '1' :
      zeitbis = int(zeitbis)+1
      #ret_dict['STATUS']='ERR'
      #ret_dict['RESULT']="""SACHB %s hat bereits am %s zur Zeit %s einen Eintrag!""" %(sachb, datumbis, zeitbis)
      #return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')

   # Datumsvalidierung
   if str(validate_mydate(datumvon,dbconn))[0:3] == 'DAT':
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(validate_mydate(datumvon,dbconn))
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
      

   if str(validate_mydate(datumbis,dbconn))[0:3] == 'DAT':
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(validate_mydate(datumbis,dbconn))
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')

   # Einfügen des Datensatzes mit TERMID='HTTP', POSNR=1, Hinweis='HTTP-Zeitbuchung', FEHLEROK='N', SACHBEARBEITER.VORNAME / NACHNAME (wenn vorhanden)

   sql = """insert into ZEITROHDATEN (SACHB, BWART, DATUM, ZEIT, TERMID, TRANSNR, POSNR, HINWEIS, FEHLEROK, VORNAME, NACHNAME) 
           select UPPER('%s'), UPPER('%s'), '%s', '%s', 'HTTP', nextval('SEQ_TRANSNR'), 1 , 'HTTP-Zeitbuchung', 'N', coalesce(VORNAME,''), coalesce(NAME,'')
           from SACHBEARBEITER
           where SACHB = UPPER('%s')
           and not exists ( select 'X'
                            from   ZEITROHDATEN X
                            where  X.SACHB = UPPER('%s') 
                            and    X.BWART = UPPER('%s') 
                            and    X.DATUM = '%s'
                            and    X.ZEIT = '%s') """ %(sachb, bwart, datumvon, zeitvon, sachb, sachb, bwart, datumvon, zeitvon)

   erg = context.sql_execute(dbconn,sql)
   #context.script_log('/sync/rohdaten_import erg: ', str(erg))
   error = erg[0]
   if len(error) > 0:
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(error[0])
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   #Abfragen der Transaktionsnummer1
   sql="""select TRANSNR
          from ZEITROHDATEN
          where SACHB=UPPER('%s')
          and BWART = UPPER('%s')
          and DATUM = '%s'
          and ZEIT = '%s' """ %(sachb, bwart, datumvon, zeitvon)

   #context.script_log('/sync/rohdaten_import  SQL: ', str(sql))
   erg = context.sql_execute(dbconn,sql)
   #context.script_log('/sync/rohdaten_import erg: ', str(erg))
   error = erg[0]
   data = erg[1]
   if len(error) > 0 :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(error[0])
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   else: 
      for line in data:
         transerg+= str(line[0])+'_'
   if bwart.upper() == 'KO' :
      bwart='GT'
   # Einfügen des zweiten Zeitstempels mit Datumbis und Zeitbis
   sql = """insert into ZEITROHDATEN (SACHB, BWART, DATUM, ZEIT, TERMID, TRANSNR, POSNR, HINWEIS, FEHLEROK, VORNAME, NACHNAME) 
            select UPPER('%s'), UPPER('%s'), '%s', '%s', 'HTTP', nextval('SEQ_TRANSNR'), 1 , 'HTTP-Zeitbuchung', 'N', coalesce(VORNAME,''), coalesce(NAME,'')
            from SACHBEARBEITER
            where SACHB = UPPER('%s')
            and not exists ( select 'X'
                             from   ZEITROHDATEN X
                             where  X.SACHB = UPPER('%s') 
                             and    X.BWART = UPPER('%s') 
                             and    X.DATUM = '%s' 
                             and    X.ZEIT = '%s') """ %(sachb, bwart, datumbis, zeitbis, sachb, sachb, bwart, datumbis, zeitbis)

   #context.script_log('/sync/rohdaten_import  SQL: ', str(sql))

   erg = context.sql_execute(dbconn,sql)
   #context.script_log('/sync/rohdaten_import erg: ', str(erg))
   error = erg[0]
   if len(error) > 0 :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(error[0])
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')

   # Abfragen der Transaktionsnummer2
   sql="""select TRANSNR
          from ZEITROHDATEN
          where SACHB=UPPER('%s')
          and BWART = UPPER('%s')
          and DATUM = '%s'
          and ZEIT = '%s' """ %(sachb, bwart, datumbis, zeitbis)

   #context.script_log('/sync/rohdaten_import  SQL: ', str(sql))

   erg = context.sql_execute(dbconn,sql)
   #context.script_log('/sync/rohdaten_import erg: ', str(erg))
   error = erg[0]
   data = erg[1]
   if len(error) > 0 :
      ret_dict['STATUS']='ERR'
      ret_dict['RESULT']=str(error[0])
      return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   else:
      for line in data:
         transerg+= str(line[0])

   # verarbeitungsdatum löschen an allen eintraegen des tages
   sql = """update zeitrohdaten set verarbdatum=null 
            where 
            sachb = UPPER('%s')
            and 
            datum = '%s'
   """ % (sachb, datumvon)
   erg_verarb = context.sql_execute(dbconn,sql)

   ret_dict['STATUS']='OK'
   ret_dict['RESULT']=str(transerg)
   return json.dumps(ret_dict, ensure_ascii=False, encoding='utf8')
   
  
except:
   # Ende mit Fehler.
   val_dict = {'STATUS' : '', 'RESULT' : ''}
   tb = sys.exc_info()[2]
   info = str(sys.exc_info()[0]) + str(traceback.format_tb(tb))
   context.script_log('FEHLER in  /sync/rohdaten_import: ', str(info))
   val_dict['STATUS']='ERR'
   val_dict['RESULT']=str(info)
   return json.dumps(val_dict, ensure_ascii=False, encoding='utf8')
