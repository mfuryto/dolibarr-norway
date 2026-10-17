# Norske standardkontoer og lukkingskontoer

Norsk regnskapsoppsett åpner en samlet oversikt over 46 kontofelt fra Dolibarr
24.0.1: standardkontoene under Regnskap og de to resultatkontoene under
årsavslutning. Dette er kontoforslag knyttet til funksjon, ikke en automatisk
kontering av alle transaksjoner eller dokumentasjon på NS 4102-samsvar.

## Bruk

Installer og velg først den medfølgende norske kontoplanen. Velg deretter bare
kontoer som passer virksomhetens faktiske bruk. Ingenting er forhåndsvalgt.
Forhåndsvisningen viser hvilke tomme felt som kan fylles. Bekreft valgene før
lagring. Eksisterende lokale, arvede og effektive valg beholdes. Kontoene må
være aktive og ha uendret nummer og navn fra profilen. Lagrede fakturaer som
ikke er utkast, eller hovedboksposteringer, sperrer nye standardvalg gjennom
hjelperen. I et eksisterende regnskap må endringer vurderes særskilt i kjernens
oppsett; hjelperen flytter ingen tidligere posteringer.

Vare-, tjeneste-, kunde-/leverandør- og MVA-satskoblinger kan overstyre disse
reservekontoene. Å velge en MVA-konto registrerer ikke foretaket i
Merverdiavgiftsregisteret og avgjør ikke sats eller fradragsrett.

## Faggrunnlag og lukkingskontoer

[Tripletex Kontohjelp](https://www.tripletex.no/kontohjelp/) beskriver kontoenes
bruk. For ENK viser resultatdisponeringen 2050 mot 8800, med motsatt føring ved
tap. For AS omtales 2050 mot 8960 og udekket tap mot 8990. Beskrivelsen av 2080
presiserer at annen egenkapital må tas i betraktning først. Derfor er 2080 ikke
et generelt standardvalg for ethvert underskudd. Denne oversikten gir ingen
regel som automatisk fordeler tap eller disponerer resultatet.

Koblingen til Dolibarr er prosjektets tekniske vurdering av
[kjernens resultatpostering ved åpning av neste periode](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/accountancy/class/bookkeeping.class.php#L3194).
Der velges `ACCOUNTING_RESULT_PROFIT` eller `ACCOUNTING_RESULT_LOSS` og
resultatet føres som egenkapital i inngående balanse. Feltene foreslår derfor
2050, med 2080 som særskilt tapsalternativ. Resultatdisponeringskontoene 8800,
8960 og 8990 er ikke alternativer i disse feltene.

**Valg av lukkingskontoer er ikke et ferdig årsoppgjør.** Kjernens
[årsavslutningsoppsett](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/accountancy/admin/closure.php)
krever også journal og riktige kontogrupper for balanse og resultat.
Kontoplanimporten kvalifiserer ikke disse gruppene. Automatisk norsk
årsavslutning er ikke akseptert produksjonsomfang. Ingen årsavslutning eller
postering utføres av standardkontohjelperen.

## Andre kilder og avgrensninger

Kontoenes numre og navn kontrolleres mot den låste
[Odoo-kontoplanen](https://github.com/odoo/odoo/blob/2ff84495182bc2d6e87b616731f865677a12b213/addons/l10n_no/data/template/account.account-no.csv).
Denne profilen er kilde til kontonavnene og ikke en offentlig godkjenning av
alle koblingene. Feltlisten kontrolleres mot
[Dolibarrs standardkontooppsett](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/accountancy/admin/defaultaccounts.php).

- [Agroøkonomi om MVA-oppgjør](https://agro.zendesk.com/hc/no/articles/4402358424084-Differanse-p%C3%A5-konto-2740-Oppgj%C3%B8rskonto-mva-i-forhold-til-reell-gjeld-eller-tilgodehavende) forklarer forholdet mellom 2700, 2710 og 2740. Standardkontoene er ikke en MVA-melding.
- [Altinn om ENK](https://info.altinn.no/enk) forklarer at innehaveren ikke er ansatt. Lønns- og ansattutleggskontoer er derfor ikke kontoer for innehaverens privatuttak.
- [Tripletex om programvareanskaffelse](https://www.tripletex.no/kontohjelp/konto/6552-programvare-anskaffelse/) og [Fiken om programvare](https://kontohjelp.fiken.no/as/medMva/6553) gir eksempler på avgrensede kostnadstyper. Ingen enkelt tjenestekonto passer alle kjøp, og aktivering må vurderes separat.
- [Fiken om 6795](https://kontohjelp.fiken.no/6795) forklarer avgrensningen mot opplysningspliktige tjenester. Forslaget må velges etter leveransens art og opplysningsplikt.

Kildene ble kontrollert 2026-10-12. UI-tekstene forklarer formål og forbehold
for hvert felt. Felt uten kvalifisert standardforslag vises som «må vurderes
separat». Dette gjelder blant annet omvendt avgiftsplikt,
lokale skatter, rabatter, gaver, medlemskap og mellomregning. Ingen norsk
MVA-konto settes automatisk i kjernens EU- eller lokale skattefelt. Utenlandsk
leveranse må vurderes etter leveransetype og mottakerforhold.

## Faktura og hovedbok

Fakturakontrollen kan sammenligne brutto fakturabeløp med sum debet og kredit
for de kildekoblede posteringene i ett bilag. Sammenligningen støtter validerte
ordinære NOK-fakturaer og kreditnotaer. Beløp beregnes med åtte desimaler uten
flyttall; debet og kredit må balansere eksakt, mens sammenligning med
fakturabeløpet har toleranse på ett øre. Manglende/avvikende kildekobling,
flere bilagsnøkler eller feil dato gir behov for gjennomgang. Avkortede
resultater godtas ikke. Tom hovedbok gir ingen godkjent sammenligning.

Dette kontrollerer bruttosummer, ikke korrekt konto-/MVA-fordeling,
betalinger, gebyrer, tilbakeføringer eller fullstendighet. Faktura og hovedbok
leses separat; oppdater visningen etter endringer. Kontrollen skriver ingen data.

## Regnskapskoder for varer og tjenester

Standardvalgene omfatter både salg og kjøp av varer og tjenester. Kodene er
kontonumre, ikke en separat norsk kodeordning. Egne koder kan angis i
«Regnskapskoder» ved redigering av vare-/tjenestekortet. Disse individuelle
kodene endres ikke av standardkontohjelperen. Tomme koder bruker kjernens
reservekoblinger; øvrige kunde- og avgiftskoblinger kan også påvirke konteringen.

Innenlandsk høy sats har forslag 3000 for varesalg og 3020 for tjenestesalg.
4300 gjelder varer for videresalg. Tjenestekjøp har flere kostnadstyper, og
krever et bevisst valg. Sats, avgiftsfritak, unntak, utenlandshandel og eventuell
aktivering kan gjøre andre kontoer nødvendige. Ikke kopier samme kode til alle
varer og tjenester uten å vurdere hva de faktisk gjelder.

Felt og prioritering kontrolleres mot kjernens
[vare-/tjenestekort](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/product/card.php)
og [kobling av fakturalinjer](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/accountancy/customer/index.php).

## Gjennomgang av kodene på kortene

Fra 0.38.0 åpner det tidligere oppsettets produktoppgave en lesende oversikt over kontokandidater
for varer og tjenester. Oversikten finnes også fra standardkontosiden.
Den viser kortets egen kode, aktuell standardkonto og om kontokandidaten
finnes entydig og er aktiv i valgt kontoplan. Vare-/tjenestereferansen åpner
kortet for vanlig redigering. Ingenting lagres av denne oversikten.

Innenlandsk, eksport og EU-felter kan gjennomgås separat. Valget inspiserer
lagrede felt; det avgjør ikke hvilken handelstype en faktisk leveranse har.
EU-feltene er ikke en generell kode for handel mellom Norge og EU. Produkter
av type vare/tjeneste som er markert for salg eller kjøp i aktivt foretak tas
med, med bare de relevante retningene. Første 200 kort vises i fast rekkefølge;
avkorting varsles og betyr at gjennomgangen ikke er fullstendig.

Dette er ikke en simulering av fakturakontering. Vist MVA-sats er dagens
kortstandard. Fakturalinjer, tredjepartskontoer, forskudd, hooks og andre
moduler kan gi andre resultater. Tilgjengelig konto bekrefter ikke korrekt
kostnadstype eller avgiftsbehandling. Egne ugyldige/inaktive koder skjules
ikke ved å bytte dem ut med en standardkonto i visningen.

Den tekniske prioriteringen følger produkt- og standardfeltene i
[Dolibarrs getAccountingCodeToBind](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/accountancy/class/accountingaccount.class.php#L744),
kontrollert mot den låste kjernen. Ingen nye norske avgiftsregler er innført.

Fra 0.39.0 finnes [generelle kontoforslag for handel](trade-account-suggestions.md)
med egne import-/eksportvalg og flere kostnadstyper. Forslagene følger
medfølgende kontoplan og krever eksplisitt valg etter formål og avgiftsbehandling.

Fra 0.40.0 kan tomme salg- og kjøpskontoer på ordinære norske MVA-satser
fylles separat med forhåndsvisning og bekreftelse. Standardkontoene fyller
ikke satsradens egne felt. Se [MVA-kontoer](vat-accounts.md) for bruk og omfang.
