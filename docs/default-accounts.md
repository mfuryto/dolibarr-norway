# Bekreftet utfylling av kunde- og leverandørkonto

Fra 0.13.0: Åpne norsk oppsettsveiviser → steg 4 → **Lagre kunde- og leverandørkonto**. Siden finnes også fra kontoplan-/bokføringskontrollen. Administrator og aktiv Norway-modul kreves.

Forhåndsvisningen viser lagret og eventuell avvikende effektiv verdi, forslag og handling for kunde (1500 Kundefordringer) og leverandør (2400 Leverandørgjeld). Disse to reskontrostandardene er hele lagringsomfanget. Salgs-, kjøps- og MVA-kontoer, journaler, bankkontoer, produkter, motparter og bilag endres ikke. Kundens eller leverandørens egen kobling kan overstyre standarden.

Eksisterende, arvede og effektive ikke-tomme valg bevares, også andre kontonumre. Tom streng eller -1 kan fylles bare når aktiv norsk NORWAY-NO-1-plan er valgt både i databasen og i kjørende oppsett, og kontoen finnes én gang i aktivt foretak, er aktiv og har uendret navn fra den låste kilden. Utfylling sperres ved hovedbokhistorikk eller kunde-/leverandørfaktura med annen status enn kladd. Dette hindrer endring av reservekoblinger for allerede utstedte, men ennå ikke bokførte fakturaer. Kladd er ikke historikk i denne kontrollen. Andre dokumenttyper, eksterne importer eller tillegg kan ha egne koblinger som må vurderes manuelt.

Kontroller forhåndsvisningen og ha fersk backup før du bekrefter. Ingenting lagres ved åpning, modulaktivering eller oppdatering. Bekreftelsen er bundet til bruker, foretak og forhåndsvisning i ti minutter og kan brukes én gang. Ved lagring låses og leses oppsett, kontoer og relevante dokument-/hovedbokområder på nytt. Endret forhåndsvisning avvises. De to lagringene skjer i én transaksjon via kjernens konstantfunksjon, og lagret verdi kontrolleres. En delvis feil rulles tilbake. Eksisterende innstillinger i minnet gjenopprettes ved feil.

Ved ukjent resultat eller tilkoblingsfeil: åpne siden på nytt og kontroller lagrede verdier før nytt forsøk. En fersk forhåndsvisning etter vellykket utfylling viser bevarte valg og ingen ny lagringsknapp. Når utfylling er sperret av historikk eller kontoplan, brukes lenken til manuelle standardkontoer etter vurdering av virkningen; funksjonen tilbyr ingen overstyringsknapp.

Det finnes ingen samlet regnskapsmessig produksjonsgodkjenning. Eksisterende valg som bevares er ikke automatisk riktige. Fullt endringsspor for regnskapet, øvrige standardkontoer og lagring av veiviserfremdrift er fortsatt videre arbeid.

## Verifikasjon

Syntetiske databasetester dekker to nye valg, ny kjøring uten endring, egne/arvede/effektive valg, inaktiv konto, endret forhåndsvisning, kunde-/leverandørfakturahistorikk, kladd, hovedbokhistorikk, administrator-/foretaksvern og feil på andre lagring med tilbakeføring av DB og minne. HTTP-test kontrollerer sperret eksisterende oppsett og CSRF. En separat, uttrykkelig sikret test av fersk installasjon prøver bekreftet lagring og avviser gjenbruk av bekreftelsen. Backup-/restore-testen kontrollerer at de bekreftede standardene bevares. Ingen nye tabeller eller migreringer innføres.
