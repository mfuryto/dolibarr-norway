# Fakturakontroll for periode

Fra 0.26.0: åpne Norsk oppsettsveiviser → steg 7 → Flere kontroller og innstillinger
ved behov → Fakturakontroll for periode. Den kan også åpnes fra den enkelte
fakturakontrollen. Administrator med fakturaleserettighet og aktiv Norway-modul kreves.

Velg fra- og til-dato og trykk «Vis fakturakontroll». Utvalget bruker fakturadato,
inkluderer begge datoer og gjelder bare aktivt foretak. Dokumenter uten fakturadato
inngår ikke. Dette bruker ikke betalingsdato, bokføringsdato eller avgiftstidspunkt.

Alle statuser og fakturatyper inngår, også utkast, proforma, kreditnota og
annullerte/avbrutte dokumenter. Type og status vises på hver rad. Ingen av disse
klassifiseres automatisk som avgiftspliktig omsetning. Lagret netto og MVA i
foretakets grunnvaluta vises med opprinnelige fortegn, også på kreditnota.

[Beløpskontrollen](invoice-amounts.md) sammenligner lagrede linjesummer med hver
fakturas totalfelter innen ett øre per total. Samsvar godkjenner ikke sats eller
avgiftsbehandling. Nullsats/null MVA og manglende linjer fremgår. Valuta utenfor
NOK-omfanget varsles, uten omregning. Åpne dokumentlenken for å undersøke avviket.

Listen viser høyst 200 dokumenter, sortert etter dato og intern ID. Hvis flere
finnes, vises tydelig avkortingsvarsel. Velg mindre datointervaller; det finnes
foreløpig ikke sideinndeling. Selv en uavkortet liste er bare dokumentutvalget
beskrevet her, ikke dokumentasjon av at alle regnskapsbilag er med.

Det beregnes ingen beløpssummer på tvers av dokumentene. Leverandørfakturaer,
fradragsrett, historisk avgiftsmetadata, avgiftskoder, rapporteringsmapping,
hovedboksavstemming og MVA-melding inngår ikke. Dagens oppsett brukes ikke som bevis
på historisk behandling. Det finnes ingen samlet godkjenningsstatus.

Kontrollen bruker en lesetransaksjon og kjernens fakturalesing. Database-/lesefeil
avbryter hele listen, fremfor å vise delresultater eller en tom periode som om
lesingen lyktes. Ingen fakturaer, innstillinger, dokumentfiler eller perioder
endres. Det er ingen nye tabeller eller migreringer.

Testene bruker syntetiske fakturaer og dekker datoavgrensning, aktive foretak,
alle statuser, proforma, kreditnotafortegn, avvik, tom periode, dokumenter uten
linjer/dato, avkorting og lesefeil. Autentisert visning prøves på norsk/engelsk,
og uautentisert tilgang avvises. Breddekontrollen inkluderer en utfylt periode.

Fra 0.27.0 vises også antall linjer med tom avgiftskode, særmarkering eller utilgjengelig avgiftsopplysning. Se [lagrede avgiftsopplysninger](invoice-tax-evidence.md). Dette er ingen automatisk feilklassifisering eller rapporteringsmapping.
