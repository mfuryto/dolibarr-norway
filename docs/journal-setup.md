# Bekreftet oppretting av manglende journaltyper

Fra 0.14.0: Åpne norsk oppsettsveiviser → steg 4 → **Opprett manglende journaltyper**. Lenken finnes også i kontoplan-/bokføringskontrollen. Administrator og aktiv Norway-modul kreves.

Forhåndsvisningen viser eksisterende aktive journaler med kode og forslag per type. Forslagene er SALG (salg, natur 2), KJOP (kjøp, 3), BANK (bank, 4) og DIV (diverse, 1). Dette er prosjektets standardkoder, ikke norske myndighetskoder. Journalnavnene lagres på norsk; forklaringene finnes på norsk og engelsk.

Hvis en aktiv journal med ikke-tom kode allerede dekker typen, beholdes den uansett kode og navn. Flere journaler av samme type bevares uten automatisk valg av én journal. Inaktive journaler aktiveres ikke, og journaler uten kode teller ikke som dekning. Hvis en foreslått kode allerede finnes uten at typen er dekket, sperres hele opprettingen for manuell gjennomgang. Sammenligning av de foreslåtte kodene tar høyde for små/store bokstaver og omkringliggende mellomrom; eventuelle andre databasekonflikter avviser transaksjonen.

Ingen journaler opprettes ved åpning, oppdatering eller modulaktivering. Ha fersk backup og gjennomgå forslagene før bekreftelse. Engangsbekreftelsen gjelder samme bruker, foretak og forhåndsvisning i ti minutter. Ved lagring låses journalområdet for aktivt foretak og leses på nytt. Endringer siden forhåndsvisningen avvises. Nye journaler opprettes via Dolibarrs AccountingJournal-klasse i én transaksjon. En delvis feil rulles tilbake. Ny kjøring etter full oppretting lager ingen dubletter.

Oppretting av nye journaler kan brukes også når foretaket har historikk, fordi eksisterende journaler, bilag, kontokoblinger og bankkontoer bevares. Dette er forskjellig fra utfylling av reservekontoer, som kan påvirke ennå ikke bokførte fakturaer. Historiske bilag flyttes ikke til de nye journalene.

En opprettet bankjournal kobles ikke automatisk til en bankkonto. Fortsett til steg 5 og velg bankjournal på det aktuelle bankkontokortet. Salgs-/kjøpsjournalene brukes gjennom kjernens journaloverføring; listen over journaltyper bekrefter ikke at bilagene er riktig bokført. Kasse, flere bankkonti, særskilte journaler og egne koder må vurderes i installasjonen.

Ved ukjent resultat: åpne forhåndsvisningen på nytt og kontroller registeret før nytt forsøk. Ingen samlet produksjonsgodkjenning innføres. Regnskapsfaglig kontomapping, MVA, EHF og SAF-T er fortsatt egne leveranser. Veiviserens manuelle arbeidsstatus er levert separat i 0.24.0.

## Verifikasjon

Syntetiske databasetester dekker tomt register, komplettering av tre manglende typer, gjentakelse uten endring, bevaring av flere egne journaler, andre foretak, inaktiv kodekonflikt, feil type, tom kode, stale forhåndsvisning, administrator-/foretaksvern og feil på andre oppretting med full rollback. HTTP-test dekker tilgang, forhåndsvisning og CSRF. En separat sikret installasjonstest prøver bekreftet oppretting av fire journaler og avvisning av gjenbruk, før backup/restore kontrollerer at kodene og typene er bevart. Ingen tabeller eller migreringer innføres.
