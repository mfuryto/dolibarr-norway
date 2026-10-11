# Installere på VPS: regnskap.thunold-olsen.no

Kontrollert 2026-10-11. Oppsettet installerer **Dolibarr 24.0.1, MariaDB og den
norske modulens grunnlag** sammen. Ingen separat nedlasting av Dolibarr eller
manuell opplasting av modulen trengs. Docker-imaget bygges med modulen inkludert.
Modulen aktiveres automatisk; land NO, basisvaluta NOK og språk nb_NO settes.
Ingen MVA-status eller norske satser/kontoplan settes automatisk.

Dette er foreløpig en installasjon for utprøving. Norsk MVA-rapport og SAF-T er
ikke implementert; ikke bruk denne som ferdig løsning for reelt norsk regnskap.
Det eksisterende testmiljøet med syntetiske bilag brukes ikke som datagrunnlag.

## Forutsetninger

VPS med Linux, SSH, Docker Engine med Compose-plugin, Git og Python 3.
Eksemplet bruker Nginx på vertsmaskinen som HTTPS-proxy. Har du allerede Caddy,
Traefik eller et kontrollpanel, bruk dets domene-/sertifikatoppsett i stedet for
å starte enda en proxy på port 80/443. Backend er `http://127.0.0.1:8088` når
proxyen kjører på samme vert. En proxy i container må bruke et tilpasset delt
Docker-nettverk; dens localhost peker ikke på verten.

DNS A (og eventuell AAAA) må peke på VPS-en. Tillat 80/443 til proxyen og
SSH etter dine tilgangsregler. Ikke åpne databaseporten eller 8088 offentlig.
Compose binder backend til localhost og eksponerer ikke databasen.

## 1. Hent prosjektet og lag innstillinger

Kjør som bruker med Docker-tilgang, i en katalog du eier:

```sh
git clone --branch staging https://github.com/mfuryto/dolibarr-norway.git
cd dolibarr-norway
git config core.hooksPath .githooks
python3 scripts/init-install-env.py --company-name 'DITT REGISTRERTE FORETAKSNAVN'
```

Bruk det faktiske foretaksnavnet. Installeringsskriptet oppretter `deploy/.env`
med tilfeldige passord og en varig instansnøkkel, med filmodus 0600. Det nekter
å overskrive eksisterende innstillinger. Passord skrives ikke i terminalen.
Standard administrator heter `regnskapadmin`; annen innlogging kan velges med
`--admin-login`. URL kan velges med `--url https://ditt.domene`.

Filen skal aldri i Git eller deles i chat. Ta privat sikkerhetskopi av den.
`INSTANCE_KEY` må bevares ved reinstallasjon/restore: den brukes av Dolibarrs
kryptering. Endring av passord i miljøfilen alene oppdaterer ikke eksisterende
bruker/database eller den lagrede Dolibarr-konfigurasjonen.

## 2. Sett opp domene og HTTPS

Hvis Nginx allerede håndteres av et kontrollpanel, tilpass dette gjennom panelet.
På Debian/Ubuntu med Nginx og Certbot installert er et eksempel:

```sh
sudo cp deploy/nginx-regnskap.conf /etc/nginx/sites-available/regnskap.thunold-olsen.no
sudo ln -s /etc/nginx/sites-available/regnskap.thunold-olsen.no /etc/nginx/sites-enabled/regnskap.thunold-olsen.no
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx --redirect -d regnskap.thunold-olsen.no
sudo certbot renew --dry-run
```

Opprett ikke en ekstra domeneoppføring hvis en allerede finnes; rediger den
relevante oppføringen. Kontroller eksisterende Nginx-oppsett og DNS før kommandoene.
Ikke logg inn via ukryptert HTTP. Backend startes i neste trinn, etter sertifikatet.
Certbot legger til TLS og videresending fra HTTP. Proxyen setter protokollheaderen
slik at Dolibarr gjenkjenner HTTPS og kan sette sikre sesjonscookies.

## 3. Bygg og start installasjonen

Fra repoets rot:

```sh
docker compose --env-file deploy/.env -f deploy/compose.yaml config --quiet
docker compose --env-file deploy/.env -f deploy/compose.yaml up -d --build
docker compose --env-file deploy/.env -f deploy/compose.yaml logs --tail 30 web
docker compose --env-file deploy/.env -f deploy/compose.yaml ps
```

Første oppstart oppretter databasen og aktiverer modulen. Vent til web er klar;
se etter bekreftelsen om norsk modul, NOK og nb_NO i loggen. Oppstart kan ta noen
minutter. Åpne **https://regnskap.thunold-olsen.no**. Logg inn som `regnskapadmin`
med `ADMIN_PASSWORD` fra den private `deploy/.env`-filen (les filen lokalt på serveren).
Ingen syntetiske regnskapsbilag eller testbruker legges inn.

Kontroller Innstillinger → Moduler: norsk lokalisering og Advanced Accounting
skal være aktive. Åpne modulens innstillingsside for status. Konfigurer deretter
foretaksopplysninger og nødvendige standardmoduler i Dolibarr. MVA-registerstatus,
kontoplan, kontokoblinger og fakturanummerering må avklares separat.

## Data, oppdatering og backup

Dette bruker et eget Compose-prosjekt `dolibarr-norway-accounting` og egne volumer
for database, dokumenter og Dolibarr-konfigurasjon. Modulfilene følger Docker-imaget.
Ikke bland miljøet med prosjektets `compose.yaml`, som er syntetisk utviklingstest.

Normal stopp og start bevarer data:

```sh
docker compose --env-file deploy/.env -f deploy/compose.yaml stop
docker compose --env-file deploy/.env -f deploy/compose.yaml start
```

Ikke bruk `down --volumes` på en installasjon med data; det sletter volumene.
Ta konsistent backup av DB, dokumenter, konfigurasjon og den private miljøfilen
før oppdatering. Bevar instansnøkkelen. Se test-and-operations.md for restore-krav.
Ved restore må hele settet og korrekt kilde-/containerrevisjon brukes sammen.

Staging er utvikling. Noter `git rev-parse HEAD` ved installasjon og oppdater
bare bevisst til en kontrollert revisjon; ikke sett opp automatisk `git pull`
og omstart. Main brukes først når et stabilt omfang er promotert dit. Modulen
støtter bare Dolibarr 24.0.1; oppgrader ikke kjernen uavhengig av kompatibilitetstest.
Ved godkjent moduloppdatering bygges imaget på nytt; vedlegg/DB/conf blir i volumene.

## Kilder

[Offisielt Docker-oppsett](https://github.com/Dolibarr/dolibarr-docker),
[Dolibarr installasjon](https://wiki.dolibarr.org/index.php/Installation_-_Upgrade),
[Nginx proxy-headere](https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_set_header)
og [Certbot med Nginx](https://eff-certbot.readthedocs.io/en/stable/using.html#nginx).
Init-hook og HTTPS-deteksjon er kontrollert mot den låste containeren og
Dolibarr 24.0.1. TLS og DNS på brukerens faktiske VPS er ikke satt opp i denne leveransen.
