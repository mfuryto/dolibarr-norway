Fra 0.80.0 brukes kjernens regnskapsperioder; se [periodeavslutning og retting](vat-period-close.md).
En avgrenset sperre bevarer hele native utkastet dersom godkjenning ville filtrert
bort rader i lukket periode. Den syntetiske perioden prøver datogrenser,
gjenåpning og uendret historisk rapport. Beskrivelsen nedenfor er historisk
bakgrunn for den utgåtte separate diagnostikken.

> Historisk dokumentasjon til og med 0.64.0. Funksjonen er fjernet i 0.65.0.
> Se [gjeldende plan](delivery-plan.md). Eldre tilleggshistorikk beholdes i arkivet.

# Kontroll av regnskapsperioder

Fra modul 0.19.0: åpne **Norsk oppsettsveiviser → steg 9 → Kontroll av regnskapsperioder**. Velg datoen du vil undersøke; dagens dato i installasjonens tidssone er standard. Dette er et skrivebeskyttet GET-oppslag.

Oversikten viser periodene i aktivt foretak, fra-/til-dato, åpen/lukket status, valgt standardperiode og kjernens periodeinnstilling. Begge datogrenser regnes med. To perioder som deler samme dag overlapper; perioder som slutter dagen før neste begynner, overlapper ikke. Valgt dato viser manglende dekning, én åpen/lukket periode eller overlapp. Manglende/ugyldige periodedatoer gjør dekningen usikker og krever manuell gjennomgang. Ukjent status behandles ikke som åpen.

Perioder uten sluttdato vises for manuell gjennomgang, selv om kjernen kan behandle slike intervaller i enkelte flyter. Standardperioden må finnes i aktivt foretak; dens status vises separat fra dekningen av valgt dato. En valgt standardperiode bekrefter ikke at alle bilagsdatoer dekkes.

## Periodeinnstilling og begrensning

Dolibarr 24.0.1 bruker `ACCOUNTANCY_FISCAL_PERIOD_MODE`. Verdien `blockedonclosed` kontrollerer mot lukkede perioder, mens den alternative datokontrollen bruker åpne perioder. Siden viser den faktiske innstillingen uten å endre den. Oversikten er ikke en beslutning om at en konkret postering kan utføres.

Kontrollen åpner/lukker ingen perioder, endrer ikke standardperiode, posterer ingenting og endrer ingen bilag. Ved behov brukes lenken til kjernens periodeoppsett. Før lukking må avstemming, nødvendige korreksjoner, rapportering og rettigheter gjennomgås. Full kvalifisering av låsing, retting og endringsspor gjenstår; denne siden gir ingen samlet produksjonsaksept.

Ingen bilagsdetaljer eller saldoer leses. Tester dekker skuddår, ugyldige datoer, inklusive grenser, overlapp, tilstøtende perioder, manglende datoer, ukjent status, standardvalg, foretak og databasefeil. Norsk/engelsk HTTP-visning testes ved innlogging, fersk installasjon og gjenoppretting. Kjernens tekniske grunnlag er `core/class/fiscalyear.class.php` og `accountancy/class/bookkeeping.class.php` i støttet 24.0.1.
