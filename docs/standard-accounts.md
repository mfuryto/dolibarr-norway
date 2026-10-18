# Norske standardkontoer og lukkingskontoer

Bruk **Regnskap → Innstillinger → Standardkontoer**, og kjernens
**Avslutningsoppsett** for resultatkontoer. Fra 0.77.0 er modulens overlappende
46-felts utfyllingsside fjernet; lagrede kontovalg beholdes.

Installer og velg først passende norsk kontoplan. Velg bare standardkontoer
som passer faktisk bruk. Kontonavn kan endres uten å sperre oppsettet. Samme
native skjema brukes for nye og brukte regnskap. En endret reservekobling
flytter ikke bokførte poster, men kan påvirke allerede registrerte dokumenter
som ennå ikke er overført. Vurder derfor dokumentkøen ved senere endringer.

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
Fra 0.77.0 får den medfølgende planen kontoklasse 1–2 for balanse og 3–8 for resultat, med prøvd teknisk saldoovergang. Fra 0.84.0 er [ordinært ENK-årsskifte](year-end.md) prøvd med
årsjusteringer, resultatdisponering og reskontro. Et automatisk norsk
årsoppgjørssystem og andre foretaksformer inngår ikke. Moduloppdatering utfører ingen årsavslutning eller postering.

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

Kildene ble kontrollert 2026-10-12. Kjernens felter må velges etter faktisk bruk. Dette gjelder blant annet omvendt avgiftsplikt,
lokale skatter, rabatter, gaver, medlemskap og mellomregning. Ingen norsk
MVA-konto settes automatisk i kjernens EU- eller lokale skattefelt. Utenlandsk
leveranse må vurderes etter leveransetype og mottakerforhold.

## Regnskapskoder for varer og tjenester

Standardvalgene omfatter både salg og kjøp av varer og tjenester. Kodene er
kontonumre, ikke en separat norsk kodeordning. Egne koder kan angis i
«Regnskapskoder» ved redigering av vare-/tjenestekortet. Disse individuelle
kodene endres ikke av moduloppdateringen. Tomme koder bruker kjernens
reservekoblinger; øvrige kunde- og avgiftskoblinger kan også påvirke konteringen.

Innenlandsk høy sats har forslag 3000 for varesalg og 3020 for tjenestesalg.
4300 gjelder varer for videresalg. Tjenestekjøp har flere kostnadstyper, og
krever et bevisst valg. Sats, avgiftsfritak, unntak, utenlandshandel og eventuell
aktivering kan gjøre andre kontoer nødvendige. Ikke kopier samme kode til alle
varer og tjenester uten å vurdere hva de faktisk gjelder.

Felt og prioritering kontrolleres mot kjernens
[vare-/tjenestekort](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/product/card.php)
og [kobling av fakturalinjer](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/accountancy/customer/index.php).

## Oppfølging av produktkoder

Bruk kjernens produktkort og produktkontooppsett. Modulen har ingen separat
kontrollside eller bekreftelseshistorikk for disse koblingene.
