# Kontoforslag for varer, tjenester, eksport og import

Fra 0.39.0 har standardkontohjelperen generelle forslag for alle åtte feltene
for innenlandsk salg/kjøp og eksport/import av varer og tjenester. Åpne
veiviserens steg 4 → Standardkontoer og lukkingskontoer. Eksport og import har
en egen synlig seksjon. Velg, forhåndsvis og bekreft bare det som passer
virksomheten. Ingen konto er forhåndsvalgt; eksisterende valg beholdes.
Hjelperens vern for kontoplan, uendrede aktive kontoer og regnskapshistorikk
gjelder fortsatt. Se [bruk av standardkontoer](standard-accounts.md).

## Forslag i medfølgende kontoplan

| Bruk | Kontoalternativer og formål |
|---|---|
| Innenlandsk varesalg | 3000/3010 høy sats og 3030/3040 middels sats; handelsvarer/egentilvirkede varer. 3100/3110 gjelder innenlands fritatt salg; 3200/3210 gjelder unntatt avgiftsplikt. |
| Innenlandsk tjenestesalg | 3020 høy sats, 3050 lav sats, 3120 innenlands fritatt salg, 3220 unntatt avgiftsplikt. |
| Vareeksport med kvalifisert fritak | 3105 handelsvarer eller 3115 egentilvirkede varer. |
| Tjenesteeksport med kvalifisert fritak | 3125. Dette er en annen konto enn innenlands fritatt salg på 3120. |
| Innenlandsk varekjøp | 4000/4030 råvarer høy/middels sats, 4100 varer under tilvirkning høy sats, 4300/4330 varer for videresalg høy/middels sats. |
| Vareimport | 4040/4041/4042 utenlandske råvarekjøp; 4340/4341/4342 utenlandske varer for videresalg. Alternativene skiller høy/middels sats og fritak. |
| Tjenestekjøp innenlands og fra utlandet | Velg kostnadstype fra oversikten nedenfor. Leverandørland gir ikke én universell kostnadskonto. |

Numre og fulle kontonavn kommer fra den
[låste kontoplanprofilen](https://github.com/odoo/odoo/blob/2ff84495182bc2d6e87b616731f865677a12b213/addons/l10n_no/data/template/account.account-no.csv).
Norske kontoplaner kan bruke forskjellige underkontoer. For eksempel omtaler
andre veiledninger 3120 generelt for fritatt tjenestesalg og 4320 for
vareimport. Disse eksemplene skal ikke kopieres over kontoplanprofilens egne
skiller: her heter 3120 **innenlands**, 3125 **utførsel**, og 4340 gjelder
utenlandske varer for videresalg med høy sats.

[Luca om salgsinntekter](https://www.lucaregnskap.no/kontobeskrivelser/salg-og-driftsinntekter)
forklarer skillet mellom avgiftspliktig, fritatt og unntatt salg.
[Luca om varekostnader](https://www.lucaregnskap.no/kontobeskrivelser/varekostnader)
beskriver råvarer, tilvirkning, videresalg og utenlandske kjøp. Forslagene er
prosjektets kobling mellom disse kontofunksjonene og Dolibarr-feltene, ikke et
myndighetskrav om bestemte kontonumre eller en NS 4102-sertifisering.

## Tjenestekjøp etter kostnadstype

Innenlandske kjøp og importfeltet tilbyr samme kostnadstyper. Velg etter
leveransen; flere typer kjøp krever egne tjenestekoder eller linjekoblinger.

| Type | Kontoer | Faggrunnlag |
|---|---|---|
| Fremmedytelse og underentreprise | 4500; 4550 ved opplysningsplikt | [4500](https://kontohjelp.unimicro.no/4500), [4550](https://kontohjelp.unimicro.no/4550) |
| Leie av lokale | 6300 | [Lokalleie](https://kontohjelp.unimicro.no/6300) |
| Leie av maskiner | 6400 | [Maskinleie](https://kontohjelp.unimicro.no/6400) |
| Leie av datasystemer | 6420 | [Datasystemer](https://kontohjelp.unimicro.no/6420) |
| Programvareanskaffelse og årlig vedlikehold | 6552 / 6553 | [Anskaffelse](https://kontohjelp.unimicro.no/6552), [vedlikehold](https://kontohjelp.no/kontering/kjop-av-programvare/vedlikehold) |
| Revisjon, regnskap og annen økonomisk rådgivning | 6700 / 6705 / 6720 | [Regnskapshonorar og skillet mot revisjon](https://kontohjelp.unimicro.no/6705); navn og kategorier fra kontoplanprofilen |
| Andre fremmede tjenester | 6790 opplysningspliktig / 6795 ikke opplysningspliktig | [6790 i tilsvarende kontoplan](https://www.lucaregnskap.no/kontobeskrivelser/6790), [6795](https://kontohjelp.fiken.no/6795) |
| Reklameannonser | 7320 | [Reklamekostnader](https://kontohjelp.unimicro.no/7320) |

Opplysningsplikt, periodisering og aktivering avgjøres separat. Disse
alternativene dekker vanlige kostnadstyper, ikke enhver spesialkonto. Andre
formål håndteres med konto fra kontoplanen på vare-/tjenestekortet eller i
kjernens regnskapsoppsett. 6553 er årlig vedlikehold i denne profilen og ikke
en standard for alle tjenester eller alle programvarekjøp.

## MVA og faktisk bruk

Kontovalg endrer ikke MVA-satser eller fradragsrett. Eksportkontoene tilbys
bare for leveranser der fritaket er avklart; utenlandsk kunde alene er ikke
nok. Se [Skatteetatens omtale av utførsel av tjenester](https://www.skatteetaten.no/rettskilder/type/handboker/merverdiavgiftshandboken/merverdiavgiftshandboken-2024/M-6/M-6-22/)
og [varer ut av merverdiavgiftsområdet](https://oppslag.rettskilder.skatteetaten.no/rettskilder2/type/handboker/merverdiavgiftshandboken/gjeldende/MVA2025_M-6).

[Import av varer](https://www.skatteetaten.no/bedrift-og-organisasjon/avgifter/mva/utland/import/)
krever separat vurdering av innførselsgrunnlag og import-MVA.
[Fjernleverbare tjenester fra utlandet](https://www.skatteetaten.no/satser/mva-satser-kjop-av-tjenester-fra-utlandet-svalbard-og-jan-mayen/)
kan kreve omvendt avgiftsplikt. Å lagre kostnadskontoen beregner eller
rapporterer ikke dette. MVA-melding og automatisk norsk import-MVA er fortsatt
utenfor akseptert omfang. Kildene ble gjennomgått 2026-10-12.

Dolibarr velger standardfelt blant annet etter vare-/tjenestetype og partenes
land; én standard kan ikke fordele flere kostnadstyper eller MVA-behandlinger
på egen hånd. EU-feltene er fortsatt til særskilt vurdering for norsk foretak.
Ved forskjellige leveranser må egne kort-/linjekontoer brukes og faktisk
kontering kontrolleres. [Den lesende kodeoversikten](standard-accounts.md#gjennomgang-av-kodene-p%C3%A5-kortene)
viser dagens egne koder og standardkandidater, uten å godkjenne transaksjonen.
