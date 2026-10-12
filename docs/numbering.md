# Enkle nummerserier

Fra modul 0.35.0 finnes **Nummerserier** i oppsettsveiviserens steg 7 og fra
kontrollen av dokumentoppsett. Oppdatering alene bytter ikke eksisterende
nummerering. Administrator forhåndsviser og bekrefter overgangen.

| Serie | Standard | Eksempel på neste nummer |
|---|---|---|
| Faktura | F000001 | F000002 |
| Kreditnota | K000001 | K000002 |
| Tilbud | T000001 | T000002 |
| Kunde | 00001 | 00002 |
| Leverandør | 00001 | 00002 |

Seriene er separate og stigende. Faktura, kreditnota og tilbud bruker seks sifre
med et valgbart prefiks på 1–4 store bokstaver. Kundenummer og leverandørnummer
bruker fem sifre uten prefiks. Ingen bindestrek, dato eller årlig/månedlig
nullstilling inngår. En part kan være både kunde og leverandør og ha ett nummer
i hver serie. Nedbetaling er en betaling mot fakturaen og endrer ikke
fakturanummeret.

## Overgang og startnummer

1. Avklar siste brukte numre i tidligere system og eksisterende installasjon.
2. Åpne **Oppsettsveiviser → steg 7 → Velg nummerserier**.
3. Velg startnummer for hver serie, og eventuelt andre prefikser for F/K/T.
4. Velg **Forhåndsvis nummerering**. Neste forventede nummer vises før lagring.
5. Gjennomgå overgangen og bekreft **Aktiver nummereringen**.

Startnummer er det første ønskede løpenummeret. Med prefiks F og start 1201
blir forslaget F001201. Med start 301 for kunder blir det 00301. Hvis samme serie
allerede inneholder et høyere nummer, brukes neste etter dette. Startnummer er
en nedre grense, ikke en instruksjon om å overskrive eller gjenbruke et nummer.
En forhåndsvisning reserverer ikke nummeret; kjernen velger det igjen ved
utstedelse eller oppretting.

Eksisterende referanser og kunde-/leverandørkoder beholdes. Ingen dokumenter,
partskoder, hovedboksposter, betalinger eller PDF-er omskrives. Eksisterende
numeriske numre med annet antall sifre i den samme serien sperrer dette enkle
oppsettet og krever særskilt gjennomgang. Systemet kan ikke kontrollere numre
som bare finnes i et tidligere system; administrator må avklare overgangen.

F/K/T-oppsettet låses når nye dokumenter er utstedt etter konfigureringen, eller
når den registrerte utstedte referansehistorikken er endret. Kunde-/leverandør-
startnumre låses separat når partsposter er lagt til/fjernet eller kodene endret.
Navne-/adresseendringer endrer ikke nummereringen. Betaling av en faktura endrer
heller ikke serieoppsettet. Før seriene tas i bruk kan startnumrene korrigeres
med ny forhåndsvisning og bekreftelse. Eksisterende numre beholder alltid sin verdi.

## Teknisk omfang

Eksterne nummereringsmoduler under `htdocs/custom/norway` bruker kjernens
`get_next_value` og faste masker, med startnummer minus én som innebygd offset.
Ingen kjernemodifikasjon, egen teller-tabell eller ny databasemigrering inngår.
En foretaksavgrenset innstilling `NORWAY_NUMBERING_V1` lagrer valgt oppsett,
bruker/tidspunkt og fingeravtrykk av historikken ved konfigurering.

Fakturatyper 0 og 2 får F/K. Andre fakturatyper fortsetter gjennom den tidligere
valgte, støttede kjernemodulen; forskuddsfaktura er ikke omdøpt til nedbetaling.
Tilbud får T. Kunde-/leverandørmodulen godtar uendrede historiske koder ved
redigering av en eksisterende part, også når koden har et eldre format.
Nye numre kontrolleres for format og duplikater av modulen og kjernens database.
Konto-/reskontronummer er egne felt og endres ikke av denne funksjonen.

Kjente kjernemoduler støttes ved overgang: Mars/Terre/Mercure for faktura,
Marbre/Saphir for tilbud, Leopard/Monkey/Elephant for partskoder.
Egendefinerte modeller, delte nummerserier mellom foretak og spesielle
miljøoverstyringer er ikke kvalifisert av denne leveransen.

Bekreftelsen bruker CSRF-vern, engangsverdi og ti minutters levetid, bundet til
bruker og foretak. Endret oppsett eller referansegrunnlag krever ny forhåndsvisning.
Lagringen skjer i en egen serialiserbar transaksjon med låsing og etterkontroll;
feil ruller tilbake alle tilhørende innstillinger. Administrator må ha aktiv
norsk modul. Aktivering registrerer de eksterne modellstiene; ordinær
oppdatering kontrollerer registreringen og bevarer valgt oppsett.

Det enkle oppsettet gjennomgår maksimalt 100 000 dokumenter og partsposter
samlet. F/K/T kan gå til 999999, og kunde-/leverandørseriene til 99999.
Ved grensen stoppes tildelingen; det skjer ingen automatisk omstart.
Sletting av historiske poster eller manuell manipulering av referanser inngår
ikke i støttet bruk. Kjernens masketeller bygger på eksisterende referanser;
sletting av høyeste referanse kan ellers påvirke neste nummer. Innstillingen er
ikke et manipulasjonssikkert revisjonsspor, og avanserte administrator-/API-
inngrep er ikke generelt sperret av denne oppsettsfunksjonen.

## Aksept og kilder

Syntetiske prøver dekker faktisk utstedelse av F/K/T med valgte startnumre,
separate stigende serier over årsskifte, faktisk oppretting av kunder og
leverandører, bevaring av eldre koder, låsing etter bruk, feilinjisert atomisk
lagring, endret forhåndsvisning, duplikater, foretaksavgrensning og kapasitetsgrenser.
HTTP-prøvene dekker innlogging, norsk/engelsk, bekreftelse, gjentatt innsending
og CSRF. Oppdaterings- og gjenopprettingsprøven kontrollerer at oppsett og
startnumre beholdes. Dette er teknisk aksept for avgrenset nummerering;
hele regnskapssystemet er ikke dermed godkjent for norsk produksjonsregnskap.

Teknisk grunnlag er Dolibarr 24.0.1 sin maskefunksjon og modulgrensesnitt, samt
[Dolibarrs dokumentasjon av fakturaoppsett](https://wiki.dolibarr.org/index.php/Invoice_Configuration)
og [eksterne nummereringsmoduler](https://wiki.dolibarr.org/index.php/Create_numbering_module).
Kravet gjelder kontrollerbar maskinell sekvens, ikke et bestemt bokstav-/datoformat;
se [Skatteetatens uttalelse om nummerering](https://www.skatteetaten.no/rettskilder/type/uttalelser/prinsipputtalelser/nummerering-av-salgsdokumenter/).
