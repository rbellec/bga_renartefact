BGA_USER   := GoOn
BGA_HOST   := 1.studio.boardgamearena.com
BGA_PORT   := 2022
BGA_GAME   := renartefact
BGA_REMOTE := $(BGA_GAME)
BGA_SCP    := scp -i ~/.ssh/id_rsa -P $(BGA_PORT) -o IdentitiesOnly=yes

DEPLOY_ROOT := gameinfos.inc.php dbmodel.sql stats.jsonc gameoptions.jsonc gamepreferences.jsonc
DEPLOY_PHP  := modules/php/Game.php
DEPLOY_JS   := modules/js/Game.js
DEPLOY_CSS  := renartefact.css

check:
	@php -l modules/php/Game.php
	@for f in modules/php/States/*.php; do php -l "$$f" || exit 1; done
	@php -l gameinfos.inc.php
	@echo "✓ PHP OK"

deploy: check
	$(BGA_SCP) $(DEPLOY_ROOT) $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/
	$(BGA_SCP) $(DEPLOY_CSS) $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/$(BGA_GAME).css
	$(BGA_SCP) $(DEPLOY_PHP) $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/modules/php/
	$(BGA_SCP) $(wildcard modules/php/States/*.php) $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/modules/php/States/
	$(BGA_SCP) $(DEPLOY_JS) $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/modules/js/
	@echo "✓ Deployed"

deploy-img:
	$(BGA_SCP) img/* $(BGA_USER)@$(BGA_HOST):$(BGA_REMOTE)/img/
	@echo "✓ Images deployed"

.PHONY: check deploy deploy-img
