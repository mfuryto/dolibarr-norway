# Papirløst regnskap og bilagsflyt

Vedtatt videre omfang 2026-10-13. Funksjonene inngår i roadmapens A3/C1.
Prosjekteier har prioritert papirløs bilagsflyt til slutt, etter de øvrige
planlagte funksjonsleveransene. A3 gjennomføres før C1. Arbeid med bokføring,
MVA, avstemming, rapportering og øvrige norske krav fortsetter først.
Dokumentasjons- og arkivkrav ivaretas underveis; E-sporets frister flyttes ikke.

Omfanget er forenklet etter prosjekteiers valg: vanlig bruk i nettleser på PC,
med manuell opplasting og registrering. Gjenbruk Dolibarrs eksisterende
funksjoner og hold antall skjermbilder og handlinger lavt.

## Grunnlag og mål

Dolibarr 24.0.1 har dokumentvedlegg på leverandørfakturaer og utlegg.
Disse funksjonene skal undersøkes og gjenbrukes før egne alternativer bygges.
Kjernens vedlegg, bilagsbilder, statuser, søk og registreringsflyt kartlegges
før A3. Oversikt og dokumentvisning i denne planen beskriver brukerbehov,
ikke en beslutning om å bygge nye skjermer. Gjenbruk de eksisterende
skjermbildene og dokumentlagringen der de dekker behovet.
Opplasting av en fil er ikke i seg selv registrering, godkjenning eller
bokføring av et kjøp. Den samlede norske arbeidsflyten må kvalifiseres.

Brukeren skal kunne følge originaldokumentet fra mottak gjennom behandling,
bokføring, betaling og senere kontroll. Arbeidsflyten skal fungere for varer
og tjenester på tvers av bransjer og bevare eksisterende regnskapsdata.

## A3 – første leveranse

- Enkel bilagsoversikt for PDF-er og kvitteringsbilder som lastes opp fra
  datamaskinen. Undersøk kjernens dokumentfunksjoner først.
- Dokumentvisning ved siden av registreringen, med tydelig kobling til
  leverandørfaktura, utlegg eller manuelt bilag og de faktiske posteringene.
- Enkel status for ubehandlet og bokført dokumentasjon. Betalingsstatus
  følger faktiske betalinger og avstemminger.
- Varsler om mulige duplikater og manglende dokumentasjon. Like beløp alene
  er ikke duplikatbevis; bruk dokument- og leverandørreferanser der de finnes.
- Søk og gjenfinning via dato, leverandør, dokument-/bilagsreferanse og status,
  med kontroll av brukerens rettigheter.
- Bevaring av originaldokument og nødvendige vedlegg i drift, backup,
  gjenoppretting og eksport. Forhåndsvisninger skal ikke erstatte originalen.

## C1 – etter kvalifisert bilagsflyt og avstemming

Enkel, brukerbekreftet kobling mellom tilgjengelige banktransaksjoner,
bilag, fakturaer og betalinger. Håndter delbetaling, gebyr og refusjon
innenfor avstemmingsomfanget som er kvalifisert under C.

Mobilopplasting, egen mobilapp, e-postmottak, OCR, skytolking og avansert
godkjenningsflyt inngår ikke i vedtatt bilagsomfang. Eventuelle senere
utvidelser krever et nytt konkret behov og en egen omfangsbeslutning.
E-faktura følger fortsatt det separate E-sporet og dets regelverksfrister.
Bilag og reelle personopplysninger skal aldri i Git.

Kontroller av dokumentasjon, dubletter og beløp skal følge registrering og
bokføring, og avvik skal vises på det aktuelle bilaget med en konkret neste
handling. Den som behandler bilaget har ansvar for oppfølgingen; det kreves
ikke en separat kontrollrunde eller flere godkjennere som standard. Se
[arbeidsflytkravet i roadmapen](delivery-plan.md#kontroller-som-del-av-arbeidsflyten).

## Lisens, rettigheter og selvstendig implementasjon

Funksjonskrav beskrives ut fra brukerbehov og norske regnskapskrav. Andre
produkters offentlige funksjonsbeskrivelser er referanser for behov, ikke
materiale som kopieres inn i modulen. Egen utforming skal passe Dolibarr.
Det brukes ikke tredjeparts merkevarer eller grafikk som modulens egen profil.

Lisensvilkår kontrolleres for komponentene og materialet som faktisk
gjenbrukes. Bevar nødvendige krediteringer og lisensmerknader, og bruk
komponenter som er kompatible med prosjektets GPL-3.0-or-later. Konkrete
uavklarte rettigheter må løses før det aktuelle materialet tas inn.

Gjenbruk av Dolibarr følger kjernens lisensvilkår. Proprietær kode, tekster,
skjermbilder, maler og andre beskyttede ressurser gjenbrukes bare når nødvendig
rett faktisk er dokumentert. Ingen slik gjenbruk er forutsatt i denne planen.

## Aksept og dokumentasjon

Bruk syntetiske PDF-er og bilder til testing av mottak, filkontroll, tilgang,
lesbarhet, kobling til korrekt foretak/postering, duplikater, avbrudd og
samtidige endringer. Prøv bevaring av original, slettingsvern der nødvendig,
gjenoppretting av dokumenter sammen med databasen og fullstendig eksport.

Brukerbekreftede bankkoblinger skal ikke gi doble betalinger eller
posteringer ved gjentakelse.

KR04/KR05/KR11/KR17–KR20 i [kravregisteret](norwegian-requirements.md) gjelder.
Ferdig funksjon krever dokumentert omfang, rettighetsgrunnlag, relevante tester
og godkjent leveranse gjennom staging og main. Dette dokumentet er en plan,
ikke en påstand om at bilagsflyten eller eksterne komponenter er kvalifisert.

## Referanser

- [Dolibarr: vedlegg på leverandørfakturaer, 24.0.1](https://github.com/Dolibarr/dolibarr/blob/24.0.1/htdocs/fourn/facture/document.php)
- [Dolibarr: vedlegg på utlegg, 24.0.1](https://github.com/Dolibarr/dolibarr/blob/24.0.1/htdocs/expensereport/document.php)
- [Patentstyret: programvare og rettigheter](https://www.patentstyret.no/artikler/har-du-utviklet-en-ny-programvare-software-eller-en-ny-app)
- [GNU: lisenskompatibilitet](https://www.gnu.org/licenses/license-compatibility.en.html)

Referansene er kontrollert 2026-10-13. Rettighetsvurderingen må gjelde de
komponentene og den implementasjonen som faktisk velges.
