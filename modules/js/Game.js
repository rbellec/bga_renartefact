const ART_LABEL = {
    canne: _('Canne'),
    sablier: _('Sablier'),
    chapeau: _('Chapeau'),
    loupe: _('Loupe'),
};

function artImg(type) {
    const url = g_gamethemeurl + 'img/' + type + '.svg';
    return `<img class="rnt-art-img" src="${url}" alt="${type}" draggable="false"/>`;
}
function cardBackUrl() { return g_gamethemeurl + 'img/card-back.svg'; }
function roleImg(role) {
    const name = role === 'detective' ? 'role-detective' : role === 'complice' ? 'role-complice' : 'role-hidden';
    return `<img class="rnt-role-img" src="${g_gamethemeurl + 'img/' + name + '.svg'}" alt="${name}" draggable="false"/>`;
}

class PlayerTurn {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.game.clearSelection();
        this.game.highlightActivePlayer();
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('À ${you} de jouer : une combinaison, récupérer un artéfact caché, ou défausser'));
            this.game.refreshActionButtons(args);
        } else {
            this.bga.statusBar.setTitle(_('${actplayer} joue'));
        }
    }

    onLeavingState() { this.game.clearSelection(); }
    onPlayerActivationChange() {}
}

class ViewIndice {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${you} : choisissez un indice à regarder'));
            document.querySelectorAll('.rnt-indice').forEach(el => {
                const id = parseInt(el.dataset.id, 10);
                const available = (args.availableIndices || []).some(x => x.id === id);
                el.classList.toggle('rnt-selectable', available);
                el.onclick = available ? () => {
                    this.bga.actions.performAction('actViewIndice', { indiceId: id });
                } : null;
            });
        }
    }
    onLeavingState() {
        document.querySelectorAll('.rnt-indice').forEach(el => {
            el.classList.remove('rnt-selectable');
            el.onclick = null;
        });
    }
    onPlayerActivationChange() {}
}

class ViewRole {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${you} : choisissez un joueur dont regarder le rôle'));
            (args.availablePlayers || []).forEach(pid => {
                this.bga.statusBar.addActionButton(
                    this.game.gamedatas.players[pid].name,
                    () => this.bga.actions.performAction('actViewRole', { targetPlayerId: pid })
                );
            });
        }
    }
    onLeavingState() {}
    onPlayerActivationChange() {}
}

class PickCardFromPlayer {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${you} : piochez une carte (à l\'aveugle) chez un autre joueur'));
            (args.targets || []).forEach(t => {
                for (let i = 0; i < t.hand_count; i++) {
                    this.bga.statusBar.addActionButton(
                        this.game.gamedatas.players[t.id].name + ' #' + (i + 1),
                        () => this.bga.actions.performAction('actPickBlind', {
                            targetPlayerId: t.id,
                            position: i,
                        })
                    );
                }
            });
        }
    }
    onLeavingState() {}
    onPlayerActivationChange() {}
}

class GiveCardBack {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            const targetName = this.game.gamedatas.players[args.target]?.name || '?';
            this.bga.statusBar.setTitle(
                _('${you} : vous pouvez rendre une carte à ') + targetName
            );
            this.game.setHandSelectionMode('giveback', (ids) => {
                if (ids.length === 1) {
                    this.bga.actions.performAction('actGiveBack', { cardId: ids[0] });
                }
            });
            this.bga.statusBar.addActionButton(_('Passer (ne pas rendre de carte)'),
                () => this.bga.actions.performAction('actSkipGiveBack'),
                { color: 'secondary' }
            );
        }
    }
    onLeavingState() { this.game.clearSelection(); }
    onPlayerActivationChange() {}
}

class PickFromDiscard {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${you} : choisissez 2 artéfacts différents dans la défausse'));
            const byType = args.discardByType || {};
            Object.entries(byType).forEach(([type, ids]) => {
                this.bga.statusBar.addActionButton(
                    ART_LABEL[type] + ' (' + ids.length + ')',
                    () => this.game.selectDiscardType(type, ids[0])
                );
            });
        }
    }
    onLeavingState() { this.game.discardSelection = []; }
    onPlayerActivationChange() {}
}

class ChooseCacheCard {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            const side = args.side === 'r' ? _('droite') : _('gauche');
            this.bga.statusBar.setTitle(
                _('${you} : choisissez un artéfact à cacher (cachette ') + side + ')'
            );
            this.game.setHandSelectionMode('cache', (ids) => {
                if (ids.length === 1) {
                    this.bga.actions.performAction('actPlaceCache', { cardId: ids[0] });
                }
            });
        }
    }
    onLeavingState() { this.game.clearSelection(); }
    onPlayerActivationChange() {}
}

export class Game {
    constructor(bga) {
        this.bga = bga;
        this.selection = [];
        this.handSelectionMode = null;
        this.handSelectionCallback = null;
        this.discardSelection = [];

        this.playerTurn = new PlayerTurn(this, bga);
        this.bga.states.register('PlayerTurn', this.playerTurn);
        this.bga.states.register('ViewIndice', new ViewIndice(this, bga));
        this.bga.states.register('ViewRole', new ViewRole(this, bga));
        this.bga.states.register('PickCardFromPlayer', new PickCardFromPlayer(this, bga));
        this.bga.states.register('GiveCardBack', new GiveCardBack(this, bga));
        this.bga.states.register('PickFromDiscard', new PickFromDiscard(this, bga));
        this.bga.states.register('ChooseCacheCard', new ChooseCacheCard(this, bga));
    }

    setup(gamedatas) {
        this.gamedatas = gamedatas;
        const area = this.bga.gameArea.getElement();
        area.insertAdjacentHTML('beforeend', `
            <div id="rnt-board">
                <div id="rnt-top">
                    <div id="rnt-center">
                        <div id="rnt-indices-box">
                            <div class="rnt-label">${_('Indices')}</div>
                            <div id="rnt-indices"></div>
                        </div>
                        <div id="rnt-deck-box">
                            <div class="rnt-label">${_('Pioche')}</div>
                            <div id="rnt-deck" class="rnt-pile"></div>
                            <div id="rnt-shuffle-counter"></div>
                        </div>
                        <div id="rnt-discard-box">
                            <div class="rnt-label">${_('Défausse')}</div>
                            <div id="rnt-discard" class="rnt-pile"></div>
                        </div>
                    </div>
                </div>
                <div id="rnt-players"></div>
                <div id="rnt-myhand-box">
                    <div class="rnt-label">${_('Votre main')}</div>
                    <div id="rnt-myhand"></div>
                </div>
            </div>
        `);

        this.renderIndices();
        this.renderDeck();
        this.renderDiscard();

        Object.values(gamedatas.players).forEach(p => this.renderPlayer(p.id));
        this.renderHand();
        this.highlightActivePlayer();

        this.bga.notifications.setupPromiseNotifications({});
    }

    renderIndices() {
        const el = document.getElementById('rnt-indices');
        el.innerHTML = '';
        (this.gamedatas.indices || []).forEach(ind => {
            const d = document.createElement('div');
            d.className = 'rnt-card rnt-indice';
            d.dataset.id = ind.id;
            d.dataset.pos = ind.pos;
            if (ind.seen) {
                d.classList.add('rnt-seen');
                d.innerHTML = `<div class="rnt-sym">${artImg(ind.type)}</div><div class="rnt-name">${ART_LABEL[ind.type]}</div>`;
            } else {
                d.innerHTML = `<img class="rnt-back-img" src="${cardBackUrl()}" draggable="false"/>`;
            }
            el.appendChild(d);
        });
    }

    renderDeck() {
        const el = document.getElementById('rnt-deck');
        el.innerHTML = `<div class="rnt-pile-count">${this.gamedatas.deckCount}</div>`;
        const c = document.getElementById('rnt-shuffle-counter');
        const n = this.gamedatas.shuffleCounter;
        c.innerHTML = n > 0 ? _('Compteur: ') + n : _('Compteur: —');
    }

    renderDiscard() {
        const el = document.getElementById('rnt-discard');
        const top = this.gamedatas.discardTop;
        const countHtml = `<div class="rnt-pile-count">${this.gamedatas.discardCount}</div>`;
        if (top) {
            el.innerHTML = `
                <div class="rnt-card rnt-discard-top">
                    <div class="rnt-sym">${artImg(top.type)}</div>
                    <div class="rnt-name">${ART_LABEL[top.type] || top.type}</div>
                </div>${countHtml}`;
        } else {
            el.innerHTML = `<div class="rnt-card rnt-empty">∅</div>${countHtml}`;
        }
    }

    renderPlayer(pid) {
        const players = document.getElementById('rnt-players');
        const p = this.gamedatas.players[pid];
        const state = this.gamedatas.playersState[pid] || {};
        const isMe = (parseInt(pid, 10) === parseInt(this.bga.gameui.player_id || 0, 10));
        let el = document.getElementById('rnt-player-' + pid);
        if (!el) {
            el = document.createElement('div');
            el.id = 'rnt-player-' + pid;
            el.className = 'rnt-player';
            players.appendChild(el);
        }
        const roleHtml = `<div class="rnt-role-wrap rnt-role-${state.role || 'hidden'}">${roleImg(state.role)}</div>`;
        const cacheHtml = (side, occupied) => occupied
            ? `<img class="rnt-cache-card" src="${cardBackUrl()}" draggable="false"/>`
            : '';
        el.innerHTML = `
            <div class="rnt-player-name" style="color:#${p.player_color}">${p.name || p.player_name}${isMe ? ' (' + _('vous') + ')' : ''}</div>
            <div class="rnt-player-row">
                <div class="rnt-cache-slot rnt-cache-l" data-pid="${pid}" data-side="l" title="${_('Cachette gauche')}">${cacheHtml('l', state.cache_l)}</div>
                ${roleHtml}
                <div class="rnt-cache-slot rnt-cache-r" data-pid="${pid}" data-side="r" title="${_('Cachette droite')}">${cacheHtml('r', state.cache_r)}</div>
            </div>
            <div class="rnt-hand-count">${_('Main:')} <span class="rnt-count-${pid}">${state.hand_count || 0}</span></div>
        `;
        if (isMe) {
            el.querySelectorAll('.rnt-cache-slot').forEach(slot => {
                slot.onclick = () => {
                    const side = slot.dataset.side;
                    const key = side === 'r' ? 'cache_r' : 'cache_l';
                    if (this.gamedatas.playersState[pid][key]) {
                        this.bga.actions.performAction('actRecoverCache', { side }).catch(() => {});
                    }
                };
            });
        }
    }

    renderHand() {
        const el = document.getElementById('rnt-myhand');
        el.innerHTML = '';
        (this.gamedatas.hand || []).forEach(c => {
            const d = document.createElement('div');
            d.className = 'rnt-card rnt-hand-card rnt-type-' + c.type;
            d.dataset.id = c.id;
            d.dataset.type = c.type;
            d.innerHTML = `<div class="rnt-sym">${artImg(c.type)}</div><div class="rnt-name">${ART_LABEL[c.type]}</div>`;
            d.onclick = () => this.toggleCardSelection(parseInt(c.id, 10));
            el.appendChild(d);
        });
        this.applySelectionClasses();
    }

    toggleCardSelection(cardId) {
        const idx = this.selection.indexOf(cardId);
        if (idx >= 0) this.selection.splice(idx, 1);
        else this.selection.push(cardId);
        this.applySelectionClasses();

        if (this.handSelectionMode === 'cache' || this.handSelectionMode === 'giveback') {
            if (this.selection.length > 1) {
                this.selection = [cardId];
                this.applySelectionClasses();
            }
            if (this.selection.length === 1 && this.handSelectionCallback) {
                const cb = this.handSelectionCallback;
                this.handSelectionCallback = null;
                this.handSelectionMode = null;
                cb(this.selection.slice());
            }
            return;
        }

        if (this.bga.states?.currentStateName === 'PlayerTurn') {
            this.refreshActionButtons(this.bga.gameui.gamedatas?.gamestate?.args || {});
        }
    }

    applySelectionClasses() {
        document.querySelectorAll('.rnt-hand-card').forEach(el => {
            const id = parseInt(el.dataset.id, 10);
            el.classList.toggle('rnt-selected', this.selection.includes(id));
        });
    }

    clearSelection() {
        this.selection = [];
        this.handSelectionMode = null;
        this.handSelectionCallback = null;
        this.applySelectionClasses();
    }

    setHandSelectionMode(mode, cb) {
        this.handSelectionMode = mode;
        this.handSelectionCallback = cb;
        this.selection = [];
        this.applySelectionClasses();
    }

    refreshActionButtons(args) {
        this.bga.statusBar.removeActionButtons?.();
        const sel = this.selection;
        const selectedCards = (this.gamedatas.hand || []).filter(c => sel.includes(parseInt(c.id, 10)));
        const types = selectedCards.map(c => c.type);
        const unique = Array.from(new Set(types));
        const level = args.level || this.gamedatas.level || 1;
        const myRole = args.myRole || this.gamedatas.myRole;

        if (sel.length === 2 && unique.length === 1) {
            this.bga.statusBar.addActionButton(
                _('Jouer 2 × ') + ART_LABEL[types[0]],
                () => this.bga.actions.performAction('actPlayPair', { cardIds: sel.slice() })
            );
        }
        if (sel.length === 3) {
            if (unique.length === 1 && level === 2) {
                this.bga.statusBar.addActionButton(
                    _('Jouer 3 × ') + ART_LABEL[types[0]],
                    () => this.bga.actions.performAction('actPlayTriple', { cardIds: sel.slice() })
                );
            }
            if (unique.length === 3) {
                this.bga.statusBar.addActionButton(
                    _('Jouer 3 différents'),
                    () => this.bga.actions.performAction('actPlayDifferent', { cardIds: sel.slice() })
                );
            }
        }
        if (sel.length === 4 && unique.length === 1 && myRole === 'detective') {
            this.bga.statusBar.addActionButton(
                _('Résolution: accuser ') + ART_LABEL[types[0]],
                () => this.bga.actions.performAction('actPlayResolution', { cardIds: sel.slice() }),
                { color: 'alert' }
            );
        }

        if (sel.length === 1 && !args.comboPlayedThisTurn) {
            this.bga.statusBar.addActionButton(
                _('Défausser cette carte'),
                () => this.bga.actions.performAction('actDiscardOne', { cardId: sel[0] }),
                { color: 'secondary' }
            );
        }

        this.bga.statusBar.addActionButton(
            _('Terminer le tour'),
            () => this.bga.actions.performAction('actEndTurn'),
            { color: args.comboPlayedThisTurn ? 'primary' : 'secondary' }
        );
    }

    selectDiscardType(type, cardId) {
        if (!this.discardSelection) this.discardSelection = [];
        const existing = this.discardSelection.find(s => s.type === type);
        if (existing) return;
        this.discardSelection.push({ type, cardId });
        if (this.discardSelection.length === 2) {
            const ids = this.discardSelection.map(s => s.cardId);
            this.discardSelection = [];
            this.bga.actions.performAction('actPickTwoFromDiscard', { cardIds: ids });
        }
    }

    highlightActivePlayer() {
        const active = parseInt(this.bga.gameui?.gamedatas?.gamestate?.active_player || 0, 10);
        document.querySelectorAll('.rnt-player').forEach(el => {
            const pid = parseInt(el.id.replace('rnt-player-', ''), 10);
            el.classList.toggle('rnt-active-player', pid === active);
        });
    }

    async notif_comboPlayed(args) {
        this.gamedatas.discardCount = (parseInt(this.gamedatas.discardCount, 10) || 0) + parseInt(args.count, 10);
        this.renderDiscard();
    }

    async notif_countsUpdate(args) {
        this.gamedatas.deckCount = parseInt(args.deckCount, 10);
        this.gamedatas.discardCount = parseInt(args.discardCount, 10);
        this.gamedatas.discardTop = args.discardTop || null;
        this.gamedatas.shuffleCounter = parseInt(args.shuffleCounter, 10);
        this.renderDeck();
        this.renderDiscard();
    }

    async notif_reshuffle(args) {
        this.gamedatas.deckCount = parseInt(args.deckCount, 10);
        this.gamedatas.discardCount = 0;
        this.gamedatas.shuffleCounter = parseInt(args.counter, 10);
        this.renderDeck();
        this.renderDiscard();
    }

    async notif_handUpdate(args) {
        this.gamedatas.hand = args.hand || [];
        this.renderHand();
    }

    async notif_handCount(args) {
        const pid = parseInt(args.player_id, 10);
        if (this.gamedatas.playersState[pid]) {
            this.gamedatas.playersState[pid].hand_count = parseInt(args.hand_count, 10);
            const span = document.querySelector('.rnt-count-' + pid);
            if (span) span.textContent = args.hand_count;
        }
    }

    async notif_mandatoryDiscard(args) {
        this.gamedatas.discardCount = parseInt(args.discardCount, 10);
        this.renderDiscard();
    }

    async notif_cardStolen() { }

    async notif_cardGivenBack() { }

    async notif_cardsRecovered(args) {
        this.gamedatas.discardCount = parseInt(args.discardCount, 10);
        this.renderDiscard();
    }

    async notif_cacheHidden(args) {
        const pid = parseInt(args.player_id, 10);
        const side = args.side;
        const key = side === 'r' ? 'cache_r' : 'cache_l';
        if (this.gamedatas.playersState[pid]) {
            this.gamedatas.playersState[pid][key] = true;
            this.renderPlayer(pid);
        }
    }

    async notif_cacheRecovered(args) {
        const pid = parseInt(args.player_id, 10);
        const side = args.side;
        const key = side === 'r' ? 'cache_r' : 'cache_l';
        if (this.gamedatas.playersState[pid]) {
            this.gamedatas.playersState[pid][key] = false;
            this.renderPlayer(pid);
        }
    }

    async notif_indiceRevealed(args) {
        const id = parseInt(args.card_id, 10);
        const ind = (this.gamedatas.indices || []).find(i => i.id === id);
        if (ind) {
            ind.seen = true;
            ind.type = args.type;
            this.renderIndices();
        }
    }

    async notif_indicePeeked() { }

    async notif_roleRevealed(args) {
        const tid = parseInt(args.target_id, 10);
        if (this.gamedatas.playersState[tid]) {
            this.gamedatas.playersState[tid].role = args.role;
            this.renderPlayer(tid);
        }
    }

    async notif_rolePeeked() { }

    async notif_resolutionAttempt() { }
    async notif_resolutionWin() { }
    async notif_resolutionLose() { }

    async notif_gameReveal(args) {
        const roles = args.roles || {};
        Object.entries(roles).forEach(([pid, role]) => {
            const id = parseInt(pid, 10);
            if (this.gamedatas.playersState[id]) {
                this.gamedatas.playersState[id].role = role;
                this.renderPlayer(id);
            }
        });
        if (args.stolen) {
            const banner = document.createElement('div');
            banner.className = 'rnt-reveal-banner';
            banner.innerHTML = _('Artéfact volé : ') + artImg(args.stolen) + ' ' + ART_LABEL[args.stolen];
            document.getElementById('rnt-board').prepend(banner);
        }
    }
}
