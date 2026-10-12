# Kontrollkopier av fakturagrunnlag

Fra modul 0.34.0 har fakturakontrollen lenken **Kontrollkopier av fakturagrunnlag**.
Dette er en bekreftet kopi av opplysninger som finnes ved lagringen. Kopien er
ikke en regnskapsmessig godkjenning, dokumentasjon av opprinnelig bokføringstidspunkt,
komplett fakturaarkiv, MVA-melding eller SAF-T-eksport.

## Bruk

1. Åpne en faktura fra fakturakontrollen og følg lenken til kontrollkopier.
2. Velg uttrykkelig en lagret oppsettsversjon. Nyeste versjon velges ikke automatisk.
3. Velg **Forhåndsvis kontrollkopi**. Gjennomgå linjekoder, markeringer og resultat
   mot valgt oppsettsversjon. **Alle kopierte opplysninger** viser også beløp og
   hovedboksreferanser med nøyaktig lagrede verdier.
4. Bekreft at du har gjennomgått kopien og velg **Lagre kontrollkopi**.
5. Åpne lagrede kopier for å se hva som ble kopiert. En senere lagring får nytt
   revisjonsnummer; tidligere kopier erstattes ikke.

Administrator må ha lesetilgang til fakturaer og hovedbok; norsk modul og avansert
regnskap må være aktivert. Bare fakturaer og kreditnotaer av kjernetype 0/2, med
status validert/betalt (1/2), valuta NOK, fakturalinjer og hovedboksposter støttes.
Utkast, andre dokumenttyper og andre valutaer sperres. Det kreves norsk
foretaksidentitet og gyldig historikk for kodekoblinger.

Manglende linjekoder, markeringer og koblinger uten treff beholdes i kopien;
lagringen betyr ikke at disse er avklart. Hovedbokspostene avgrenses med foretak,
dokumenttype og faktura-ID. Ingen balanse- eller fullstendighetskontroll utføres.
Inntil 200 fakturalinjer, 200 hovedboksposter og én megabyte innhold kan kopieres.
Større grunnlag avvises samlet, ikke avkortet. Maksimalt 100 kopier per faktura
støttes, med samlet innholdsgrense på fem megabyte per faktura for å begrense
minnebruk ved lesing. Grensene medfører aldri automatisk sletting.

## Innhold og lagring

JSON-format v1 inneholder foretak, organisasjonsnummer, dokument-ID/type,
fakturareferanse, status/type/dato, kunde-ID og kreditnotareferanse, NOK-kode og
fakturatotaler. Linjene inneholder ID, rekkefølge, beskrivelse, produkt-ID/type,
antall, enhetspris, rabatt, sats, lagret lokal avgiftskode, markeringer og totaler.
Hovedbokspostene inneholder ID, dokumentkoblinger, bilagsnummer, journal,
dokumentdato, referanse, konto, debet og kredit. Tall kopieres uten omregning
eller summering; databaseverdienes presisjon beholdes.

Valgt oppsettsversjon, historikken frem til denne og sammenligningsresultatet
inngår, sammen med UTC-lagringstidspunkt, bruker-ID og kopirevisjon.
Kundeopplysninger utenom kunde-ID, PDF, vedlegg, betalinger, endringshistorikk,
produktregister og fullstendig MVA-behandling inngår ikke.

Forhåndsvisningen skriver ikke til databasen. Bekreftelsen er bundet til bruker,
foretak, faktura, engangsverdi og ti minutters levetid, med CSRF-vern.
Ved lagring leses grunnlaget på nytt i en egen transaksjon med SERIALIZABLE
og radlåser på foretaksidentitet, kodekoblinger, faktura, linjer og hovedboksposter.
Endret grunnlag, oppsetthistorikk eller tidligere kopier krever ny forhåndsvisning.
Fakturaraden serialiserer samtidige lagringer; unik dokumentrevisjon er et ekstra
vern. Skrivefeil eller mislykket etterkontroll ruller transaksjonen tilbake.

Programmet har ingen oppdatering eller sletting av kopier. SHA-256 og kopiens
metadata kontrolleres ved lesing. Dette oppdager blant annet utilsiktet endret
innhold, men er ikke manipulasjonssikkert vern mot databaseadministratorer som
kan endre både innhold og kontrollsum. Revisjoner er ikke kryptografisk kjedet;
fullstendighet kan ikke bevises av kontrollsummene alene.
Personopplysninger i fakturabeskrivelser kan inngå i den private databasen og
backupen. Kopier eller eksport fra virkelige foretak skal aldri legges i Git.

Skjema v1 fra modul 0.33.0 gjenbrukes uten ny DDL eller historisk tilbakefylling.
Følg [lagring og migrering](tax-evidence-storage.md) og ordinær backupflyt.
Oppdaterings- og gjenopprettingsprøven kontrollerer at lagret tabellinnhold og
kontrollsum beholdes. Syntetiske integrasjonsprøver dekker også faktisk lagring,
endret grunnlag, nye revisjoner, skrivefeil og nettleserbekreftelse.
Dette er teknisk aksept for kontrollkopier, ikke norsk regnskapsfaglig produksjonsaksept.

## Sammenligning fra 0.36.0

Åpne en lagret kontrollkopi og velg **Sammenlign med dagens grunnlag**. Siden viser endrede fakturafelt, linjefelt og hovedboksposter med verdi i kontrollkopien og verdi nå. Nye og fjernede rader vises separat med radreferanse. Lange verdier forkortes i tabellen; kontrollkopiens detaljer beholdes. Radrekkefølge alene er ikke en endring.

Sammenligningen bruker samme uttrykkelig valgte avgiftsoppsettsversjon som kopien, slik at senere oppsettsrevisjoner ikke gir falske historiske endringer. Ingen kopier opprettes eller endres. Foretak, administratorrettigheter, kopiens integritet og dokumenttilhørighet kontrolleres før visning. Ufullstendig eller ustøttet grunnlag gir utilgjengelig sammenligning, ikke «ingen forskjeller».

Dette er sammenligning av de feltene kopien inneholder, ikke betalingshistorikk, PDF-sammenligning, opprinnelig bokføringstidspunkt eller beløps-/MVA-avstemming. Dagens opplysninger leses ved åpning; oppdater siden etter samtidige endringer. Ingen forskjeller godkjenner ikke avgiftsbehandling. Kontroller ved behov originaldokument og øvrig historikk separat.
