<?php
// customizeModal.php
// Include this file inside your page, just before </body>
// Usage: <?php include 'customizeModal.php'; ?>
?>

<!-- ===== CUSTOMIZATION MODAL OVERLAY ===== -->
<div id="customizeOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center; padding:1rem;">
    <div id="customizeModal" style="background:#fff; border-radius:20px; width:100%; max-width:500px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; transform:scale(0.92); opacity:0; transition:transform 0.25s ease, opacity 0.25s ease;">

        <!-- ── Header Banner ── -->
        <div style="position:relative; background:linear-gradient(135deg,#F5950A 0%,#C4640A 100%); padding:22px 20px 18px; text-align:center; flex-shrink:0;">
            <button onclick="closeCustomizeModal()" style="position:absolute;top:12px;right:14px;background:rgba(255,255,255,0.25);border:none;color:white;width:30px;height:30px;border-radius:50%;font-size:16px;cursor:pointer;line-height:30px;">✕</button>
            <div style="width:56px;height:56px;background:rgba(255,255,255,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
                <i class="fas fa-utensils" style="font-size:24px;color:white;"></i>
            </div>
            <p id="modalItemName"  style="color:white;font-size:17px;font-weight:700;margin:0;">Item Name</p>
            <p id="modalItemPrice" style="color:rgba(255,255,255,0.85);font-size:13px;margin:4px 0 0;">RM 0.00</p>
        </div>

        <!-- ── Quantity Bar ── -->
        <div id="modalQtyBar" style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-bottom:1px solid #f0f0f0;background:#fafafa;flex-shrink:0;">
            <span style="font-size:13px;font-weight:700;color:#333;">Quantity</span>
            <div style="display:flex;align-items:center;gap:12px;">
                <button onclick="changeModalQty(-1)" id="modalQtyMinus"
                    style="width:30px;height:30px;border-radius:50%;border:2px solid #E87D0D;background:white;color:#E87D0D;font-size:16px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s;" disabled>−</button>
                <span id="modalQtyDisplay" style="font-size:17px;font-weight:700;color:#E87D0D;min-width:24px;text-align:center;">1</span>
                <button onclick="changeModalQty(1)" id="modalQtyPlus"
                    style="width:30px;height:30px;border-radius:50%;border:none;background:#E87D0D;color:white;font-size:16px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s;">+</button>
            </div>
        </div>

        <!-- ── Loading indicator ── -->
        <div id="modalLoadingState" style="display:none;flex:1;align-items:center;justify-content:center;padding:40px 20px;text-align:center;flex-direction:column;gap:12px;">
            <i class="fas fa-spinner fa-spin" style="font-size:28px;color:#E87D0D;"></i>
            <p style="font-size:13px;color:#999;margin:0;">Loading options…</p>
        </div>

        <!-- ── Per-item instances (scrollable) ── -->
        <div id="modalInstancesContainer" style="flex:1;overflow-y:auto;padding:0 0 8px;"></div>

        <!-- ── Footer: total + confirm ── -->
        <div id="modalFooter" style="padding:14px 20px;border-top:1px solid #f0f0f0;background:#fff;flex-shrink:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <span style="font-size:13px;color:#999;">Total</span>
                <span id="modalTotalPrice" style="font-size:20px;font-weight:700;color:#E87D0D;">RM 0.00</span>
            </div>
            <button onclick="confirmAddToCart()"
                style="width:100%;padding:14px;border-radius:14px;border:none;background:#E87D0D;color:white;font-size:15px;font-weight:700;cursor:pointer;transition:background 0.15s;"
                onmouseover="this.style.background='#c96a08'" onmouseout="this.style.background='#E87D0D'">
                <i class="fas fa-cart-plus"></i> Add to Cart
            </button>
        </div>
    </div>
</div>

<!-- ===== DARK MODE + BASE STYLES ===== -->
<style>
/* ── Base structural styles ── */
.cm-instance-label {
    display:flex; align-items:center; gap:8px;
    padding:12px 20px 4px;
    font-size:11px; font-weight:700; color:#E87D0D;
    text-transform:uppercase; letter-spacing:.6px;
}
.cm-instance-badge {
    width:20px; height:20px; background:#E87D0D; color:white;
    border-radius:50%; font-size:11px; font-weight:700;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
}
.cm-instance-block { border-bottom:8px solid #f5f5f5; padding-bottom:4px; }
.cm-instance-block:last-child { border-bottom:none; }
.cm-section { padding:10px 20px 0; }
.cm-section-title {
    font-size:11px; font-weight:700; color:#999;
    text-transform:uppercase; letter-spacing:1px;
    margin:0 0 10px;
}
.cm-no-options {
    padding:24px 20px; text-align:center;
    font-size:13px; color:#aaa;
}

/* ═══════════════════════════════════════════
   DARK MODE OVERRIDES — CSS only, no JS touch
   ═══════════════════════════════════════════ */

/* Modal background */
[data-theme="dark"] #customizeModal { background:#1e1e1e !important; }

/* Quantity bar */
[data-theme="dark"] #modalQtyBar {
    background:#252525 !important;
    border-bottom-color:#333 !important;
}
[data-theme="dark"] #modalQtyBar > span { color:#ddd !important; }
[data-theme="dark"] #modalQtyMinus {
    background:#1e1e1e !important;
    border-color:#E87D0D !important;
    color:#E87D0D !important;
}

/* Footer */
[data-theme="dark"] #modalFooter {
    background:#1e1e1e !important;
    border-top-color:#333 !important;
}
[data-theme="dark"] #modalFooter > div > span:first-child { color:#888 !important; }

/* Loading state */
[data-theme="dark"] #modalLoadingState p { color:#555 !important; }

/* Scrollable instances container */
[data-theme="dark"] #modalInstancesContainer { background:#1e1e1e !important; }

/* Instance separator */
[data-theme="dark"] .cm-instance-block { border-bottom-color:#2a2a2a !important; }

/* Section titles */
[data-theme="dark"] .cm-section-title { color:#666 !important; }

/* Instance label & badge */
[data-theme="dark"] .cm-instance-label { color:#E87D0D !important; }
[data-theme="dark"] .cm-instance-badge { background:#E87D0D !important; color:#fff !important; }

/* No options placeholder */
[data-theme="dark"] .cm-no-options { color:#555 !important; }

/* ── Spice buttons ── */
[data-theme="dark"] .cm-spice-opt:not(.cm-spice-active) {
    background:#2a2a2a !important;
    color:#ccc !important;
    border-color:#3a3a3a !important;
}
[data-theme="dark"] .cm-spice-active {
    background:#E87D0D !important;
    color:#fff !important;
    border-color:#E87D0D !important;
}

/* ── Remove buttons ── */
[data-theme="dark"] .cm-remove-opt:not(.cm-remove-active) {
    background:#2a2a2a !important;
    color:#aaa !important;
    border-color:#3a3a3a !important;
}
[data-theme="dark"] .cm-remove-active {
    background:#3d1f00 !important;
    color:#E87D0D !important;
    border-color:#E87D0D !important;
}

/* ── Serve time buttons ── */
[data-theme="dark"] .cm-serve-opt:not(.cm-serve-active) {
    background:#2a2a2a !important;
    color:#ccc !important;
    border-color:#3a3a3a !important;
}
[data-theme="dark"] .cm-serve-active {
    background:#E87D0D !important;
    color:#fff !important;
    border-color:#E87D0D !important;
}

/* ── Temperature buttons ── */
[data-theme="dark"] .cm-temp-opt:not(.cm-temp-active) {
    background:#2a2a2a !important;
    color:#ccc !important;
    border-color:#3a3a3a !important;
}
[data-theme="dark"] .cm-temp-active {
    background:#E87D0D !important;
    color:#fff !important;
    border-color:#E87D0D !important;
}

/* ── Add-on rows (unchecked) ── */
[data-theme="dark"] .cm-addon-row {
    background:#2a2a2a !important;
    border-color:#3a3a3a !important;
}
[data-theme="dark"] .cm-addon-row p { color:#ddd !important; }

/* ── Add-on rows (checked/active) — orange tint ── */
[data-theme="dark"] .cm-addon-row.cm-addon-active {
    background:#3d2000 !important;
    border-color:#E87D0D !important;
}

/* ── Addon checkbox (unchecked) ── */
[data-theme="dark"] .cm-addon-cb {
    background:#2a2a2a !important;
    border-color:#555 !important;
}

/* ── Addon price label ── */
[data-theme="dark"] .cm-addon-row span[style*="E87D0D"] { color:#E87D0D !important; }

/* ── Qty display ── */
[data-theme="dark"] #modalQtyDisplay { color:#E87D0D !important; }
</style>

<!-- ===== MODAL JAVASCRIPT ===== -->
<script>
// ── Global state ──────────────────────────────────────────
let _modalItem      = { id: null, name: '', price: 0, category: '', productId: null };
let _modalQty       = 1;
let _modalRemaining = 50;
const MAX_MODAL_QTY = 50;
let _modalInstances = [];

// ── Options cache (keyed by productId) ────────────────────
const _optsCache = {};

const _EMPTY_OPTS = Object.freeze({
    spice:    false,
    beverage: false,
    dessert:  false,
    remove:   [],
    addons:   [],
});

// ── Legacy options for PRD001–PRD063 ──────────────────────
const LEGACY_OPTIONS = {
    'PRD001': { spice:false, remove:[{key:'no_sauce',label:'🥫 No Dipping Sauce'},{key:'no_chili',label:'🌶 No Chili'}], addons:[{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00},{key:'add_extra',label:'➕ Extra Portion',price:3.00}] },
    'PRD002': { spice:false, remove:[{key:'no_sauce',label:'🥫 No Dipping Sauce'},{key:'no_ginger',label:'🫚 No Ginger'}], addons:[{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00},{key:'add_extra',label:'➕ Extra Portion',price:4.00}] },
    'PRD003': { spice:false, remove:[{key:'no_sauce',label:'🥫 No Dipping Sauce'}], addons:[{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00},{key:'add_extra',label:'➕ Extra Portion',price:3.00}] },
    'PRD004': { spice:false, remove:[], addons:[{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00}] },
    'PRD005': { spice:false, remove:[{key:'no_garlic',label:'🧄 Less Garlic'},{key:'no_butter',label:'🧈 No Butter'}], addons:[{key:'add_cheese',label:'🧀 Extra Cheese',price:2.00},{key:'add_extra',label:'➕ Extra Portion',price:3.00}] },
    'PRD006': { spice:true, remove:[{key:'no_chili',label:'🌶 No Chili'},{key:'no_vinegar',label:'🍶 Less Sour'},{key:'no_egg',label:'🥚 No Egg'}], addons:[{key:'add_tofu',label:'🟨 Extra Tofu',price:2.00},{key:'add_meat',label:'🥩 Extra Meat',price:5.00}] },
    'PRD007': { spice:false, remove:[{key:'no_egg',label:'🥚 No Egg'},{key:'no_corn',label:'🌽 No Corn'}], addons:[{key:'add_meat',label:'🥩 Extra Chicken',price:5.00}] },
    'PRD008': { spice:true, remove:[{key:'no_chili',label:'🌶 No Chili'},{key:'no_lemon',label:'🍋 Less Sour'},{key:'no_coriander',label:'🌿 No Coriander'},{key:'no_lemongrass',label:'🌾 No Lemongrass'}], addons:[{key:'add_prawn',label:'🦐 Extra Prawn',price:6.00},{key:'add_mushroom',label:'🍄 Extra Mushroom',price:2.00}] },
    'PRD009': { spice:false, remove:[{key:'no_coriander',label:'🌿 No Coriander'},{key:'no_onion',label:'🧅 No Spring Onion'}], addons:[{key:'add_extra',label:'➕ Extra Wontons',price:4.00}] },
    'PRD010': { spice:false, remove:[{key:'no_cream',label:'🥛 No Cream'},{key:'no_onion',label:'🧅 No Onion'}], addons:[{key:'add_mushroom',label:'🍄 Extra Mushroom',price:2.00},{key:'add_bread',label:'🍞 Bread Roll',price:2.00}] },
    'PRD011': { spice:true, remove:[{key:'no_peanut',label:'🥜 No Peanut'},{key:'no_chili',label:'🌶 No Chili'},{key:'no_onion',label:'🧅 No Spring Onion'}], addons:[{key:'add_meat',label:'🥩 Extra Chicken',price:5.00},{key:'add_peanut',label:'🥜 Extra Peanut',price:1.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD012': { spice:false, remove:[{key:'no_pepper',label:'🫑 No Bell Pepper'},{key:'no_pineapple',label:'🍍 No Pineapple'},{key:'no_onion',label:'🧅 No Onion'}], addons:[{key:'add_meat',label:'🥩 Extra Chicken',price:5.00},{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD013': { spice:false, remove:[{key:'no_lemon',label:'🍋 Less Lemon Sauce'},{key:'no_onion',label:'🧅 No Onion'}], addons:[{key:'add_meat',label:'🥩 Extra Chicken',price:5.00},{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00}] },
    'PRD014': { spice:true, remove:[{key:'no_pepper',label:'🌑 Less Black Pepper'},{key:'no_onion',label:'🧅 No Onion'},{key:'no_capsicum',label:'🫑 No Capsicum'}], addons:[{key:'add_meat',label:'🥩 Extra Chicken',price:5.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD015': { spice:false, remove:[{key:'no_sauce',label:'🥫 No Sauce'},{key:'no_chili',label:'🌶 No Chili Flakes'}], addons:[{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00},{key:'add_extra',label:'➕ Extra Piece',price:6.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD016': { spice:true, remove:[{key:'no_onion',label:'🧅 No Onion'},{key:'no_garlic',label:'🧄 No Garlic'}], addons:[{key:'add_meat',label:'🥩 Extra Beef',price:6.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD017': { spice:true, remove:[{key:'no_pepper',label:'🌑 Less Black Pepper'},{key:'no_onion',label:'🧅 No Onion'},{key:'no_capsicum',label:'🫑 No Capsicum'}], addons:[{key:'add_meat',label:'🥩 Extra Beef',price:6.00},{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD018': { spice:true, remove:[{key:'no_onion',label:'🧅 No Spring Onion'},{key:'no_chili',label:'🌶 No Chili'},{key:'no_garlic',label:'🧄 No Garlic'}], addons:[{key:'add_meat',label:'🥩 Extra Beef',price:6.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD019': { spice:true, remove:[{key:'no_ginger',label:'🫚 No Ginger'},{key:'no_onion',label:'🧅 No Spring Onion'}], addons:[{key:'add_meat',label:'🥩 Extra Lamb',price:7.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD020': { spice:false, remove:[{key:'no_sauce',label:'🥫 No Sauce'},{key:'no_onion',label:'🧅 No Onion'},{key:'no_mushroom',label:'🍄 No Mushroom'}], addons:[{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00},{key:'add_egg',label:'🍳 Fried Egg',price:2.00},{key:'add_salad',label:'🥗 Side Salad',price:2.00}] },
    'PRD021': { spice:true, remove:[{key:'no_chili',label:'🌶 Less Chili'},{key:'no_garlic',label:'🧄 No Garlic'},{key:'no_coriander',label:'🌿 No Coriander'}], addons:[{key:'add_prawn',label:'🦐 Extra Prawn',price:6.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00},{key:'add_bread',label:'🍞 Mantou (Bun)',price:2.00}] },
    'PRD022': { spice:false, remove:[{key:'no_chili',label:'🌶 No Chili'},{key:'no_curry_leaf',label:'🌿 No Curry Leaf'}], addons:[{key:'add_prawn',label:'🦐 Extra Prawn',price:6.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD023': { spice:false, remove:[{key:'no_ginger',label:'🫚 No Ginger'},{key:'no_onion',label:'🧅 No Spring Onion'},{key:'no_chili',label:'🌶 No Chili'},{key:'no_coriander',label:'🌿 No Coriander'}], addons:[{key:'add_sauce',label:'🥫 Extra Soy Sauce',price:0.50},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD024': { spice:false, remove:[{key:'no_pepper',label:'🫑 No Bell Pepper'},{key:'no_pineapple',label:'🍍 No Pineapple'},{key:'no_onion',label:'🧅 No Onion'}], addons:[{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD025': { spice:false, remove:[{key:'no_sauce',label:'🥫 No Sauce'},{key:'no_lemon',label:'🍋 No Lemon'}], addons:[{key:'add_salad',label:'🥗 Side Salad',price:2.00},{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00}] },
    'PRD026': { spice:false, remove:[{key:'no_egg',label:'🥚 No Egg'},{key:'no_onion',label:'🧅 No Spring Onion'},{key:'no_prawn',label:'🦐 No Prawn'}], addons:[{key:'add_meat',label:'🥩 Extra Meat',price:5.00},{key:'add_egg',label:'🥚 Extra Egg',price:2.00},{key:'add_prawn',label:'🦐 Extra Prawn',price:4.00}] },
    'PRD027': { spice:false, remove:[{key:'no_egg',label:'🥚 No Egg'},{key:'no_onion',label:'🧅 No Spring Onion'},{key:'no_chili',label:'🌶 No Chili'}], addons:[{key:'add_prawn',label:'🦐 Extra Prawn',price:4.00},{key:'add_egg',label:'🥚 Extra Egg',price:2.00}] },
    'PRD028': { spice:false, remove:[{key:'no_egg',label:'🥚 No Egg'},{key:'no_onion',label:'🧅 No Spring Onion'},{key:'no_chili',label:'🌶 No Chili'}], addons:[{key:'add_meat',label:'🥩 Extra Chicken',price:5.00},{key:'add_egg',label:'🥚 Extra Egg',price:2.00}] },
    'PRD029': { spice:false, remove:[{key:'no_sausage',label:'🌭 No Chinese Sausage'},{key:'no_ginger',label:'🫚 No Ginger'},{key:'no_onion',label:'🧅 No Spring Onion'}], addons:[{key:'add_egg',label:'🥚 Extra Egg',price:2.00},{key:'add_meat',label:'🥩 Extra Chicken',price:5.00}] },
    'PRD030': { spice:true, remove:[{key:'no_onion',label:'🧅 No Fried Onion'},{key:'no_coriander',label:'🌿 No Coriander'},{key:'no_nuts',label:'🌰 No Nuts'}], addons:[{key:'add_meat',label:'🥩 Extra Chicken',price:5.00},{key:'add_sauce',label:'🥫 Extra Raita',price:1.50}] },
    'PRD031': { spice:true, remove:[{key:'no_onion',label:'🧅 No Spring Onion'},{key:'no_cabbage',label:'🥬 No Cabbage'},{key:'no_egg',label:'🥚 No Egg'}], addons:[{key:'add_meat',label:'🥩 Extra Meat',price:5.00},{key:'add_egg',label:'🥚 Extra Egg',price:2.00},{key:'add_prawn',label:'🦐 Add Prawn',price:4.00}] },
    'PRD032': { spice:true, remove:[{key:'no_chili',label:'🌶 Less Chili'},{key:'no_egg',label:'🥚 No Egg'},{key:'no_prawn',label:'🦐 No Prawn'}], addons:[{key:'add_meat',label:'🥩 Extra Meat',price:5.00},{key:'add_prawn',label:'🦐 Extra Prawn',price:4.00},{key:'add_egg',label:'🥚 Extra Egg',price:2.00}] },
    'PRD033': { spice:true, remove:[{key:'no_onion',label:'🧅 No Spring Onion'},{key:'no_chili',label:'🌶 No Chili'},{key:'no_coriander',label:'🌿 No Coriander'}], addons:[{key:'add_meat',label:'🥩 Extra Beef',price:6.00},{key:'add_egg',label:'🥚 Add Egg',price:2.00}] },
    'PRD034': { spice:true, remove:[{key:'no_chili',label:'🌶 No Chili'},{key:'no_coriander',label:'🌿 No Coriander'},{key:'no_onion',label:'🧅 No Spring Onion'}], addons:[{key:'add_prawn',label:'🦐 Extra Prawn',price:4.00},{key:'add_egg',label:'🥚 Add Egg',price:2.00}] },
    'PRD035': { spice:false, remove:[{key:'no_onion',label:'🧅 No Onion'},{key:'no_garlic',label:'🧄 No Garlic'},{key:'no_parmesan',label:'🧀 No Parmesan'}], addons:[{key:'add_cheese',label:'🧀 Extra Parmesan',price:2.00},{key:'add_meat',label:'🥩 Extra Meat Sauce',price:4.00},{key:'add_bread',label:'🍞 Garlic Bread',price:2.50}] },
    'PRD036': { spice:false, remove:[{key:'no_garlic',label:'🧄 No Garlic'},{key:'no_chili',label:'🌶 No Chili'}], addons:[{key:'add_sauce',label:'🥫 Extra Oyster Sauce',price:0.50},{key:'add_tofu',label:'🟨 Add Tofu',price:2.00}] },
    'PRD037': { spice:false, remove:[{key:'no_garlic',label:'🧄 Less Garlic'},{key:'no_chili',label:'🌶 No Chili'}], addons:[{key:'add_sauce',label:'🥫 Extra Sauce',price:0.50},{key:'add_tofu',label:'🟨 Add Tofu',price:2.00}] },
    'PRD038': { spice:true, remove:[{key:'no_carrot',label:'🥕 No Carrot'},{key:'no_mushroom',label:'🍄 No Mushroom'},{key:'no_chili',label:'🌶 No Chili'}], addons:[{key:'add_tofu',label:'🟨 Add Tofu',price:2.00},{key:'add_mushroom',label:'🍄 Extra Mushroom',price:2.00}] },
    'PRD039': { spice:true, remove:[{key:'no_chili',label:'🌶 Less Chili'},{key:'no_onion',label:'🧅 No Spring Onion'},{key:'no_mince',label:'🥩 No Minced Meat (Vegetarian)'}], addons:[{key:'add_tofu',label:'🟨 Extra Tofu',price:2.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD040': { spice:false, remove:[{key:'no_onion',label:'🧅 No Spring Onion'},{key:'no_sauce',label:'🥫 No Hoisin Sauce'},{key:'no_pancake',label:'🫓 No Pancake'}], addons:[{key:'add_sauce',label:'🥫 Extra Hoisin Sauce',price:1.00},{key:'add_pancake',label:'🫓 Extra Pancake',price:2.00}] },
    'PRD041': { spice:false, remove:[{key:'no_sauce',label:'🍯 Less BBQ Sauce'},{key:'no_onion',label:'🧅 No Onion'}], addons:[{key:'add_sauce',label:'🍯 Extra BBQ Sauce',price:1.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD042': { spice:true, remove:[{key:'no_chili',label:'🌶 Less Chili'},{key:'no_peanut',label:'🥜 No Peanut'},{key:'no_onion',label:'🧅 No Spring Onion'}], addons:[{key:'add_meat',label:'🥩 Extra Chicken',price:5.00},{key:'add_peanut',label:'🥜 Extra Peanut',price:1.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD043': { spice:false, remove:[{key:'no_curry_leaf',label:'🌿 No Curry Leaf'},{key:'no_chili',label:'🌶 No Chili'},{key:'no_egg',label:'🥚 No Egg Floss'}], addons:[{key:'add_prawn',label:'🦐 Extra Prawn',price:6.00},{key:'add_rice',label:'🍚 Add Rice',price:2.00}] },
    'PRD044': { spice:true, remove:[{key:'no_pepper',label:'🌑 Less Black Pepper'},{key:'no_mushroom',label:'🍄 No Mushroom'},{key:'no_onion',label:'🧅 No Onion'}], addons:[{key:'add_sauce',label:'🥫 Extra Sauce',price:1.00},{key:'add_egg',label:'🍳 Fried Egg',price:2.00},{key:'add_salad',label:'🥗 Side Salad',price:2.00}] },
    'PRD045': { spice:false, remove:[{key:'no_basil',label:'🌿 No Basil'},{key:'no_tomato',label:'🍅 Less Tomato Sauce'}], addons:[{key:'add_cheese',label:'🧀 Extra Cheese',price:2.00},{key:'add_extra',label:'➕ Extra Toppings',price:3.00}] },
    'PRD046': { spice:false, remove:[{key:'no_pepperoni',label:'🍕 Less Pepperoni'},{key:'no_onion',label:'🧅 No Onion'},{key:'no_chili',label:'🌶 No Chili Flakes'}], addons:[{key:'add_cheese',label:'🧀 Extra Cheese',price:2.00},{key:'add_extra',label:'➕ Extra Pepperoni',price:3.00}] },
    'PRD047': { spice:false, remove:[{key:'no_lettuce',label:'🥬 No Lettuce'},{key:'no_tomato',label:'🍅 No Tomato'},{key:'no_onion',label:'🧅 No Onion'},{key:'no_sauce',label:'🥫 No Sauce'},{key:'no_pickle',label:'🥒 No Pickle'}], addons:[{key:'add_cheese',label:'🧀 Extra Cheese',price:2.00},{key:'add_egg',label:'🍳 Fried Egg',price:2.00},{key:'add_meat',label:'🥩 Extra Patty',price:7.00},{key:'add_sauce',label:'🥫 Extra Sauce',price:0.50}] },
    'PRD048': { spice:false, remove:[{key:'no_lettuce',label:'🥬 No Lettuce'},{key:'no_tomato',label:'🍅 No Tomato'},{key:'no_onion',label:'🧅 No Onion'},{key:'no_sauce',label:'🥫 No Sauce'},{key:'no_pickle',label:'🥒 No Pickle'}], addons:[{key:'add_cheese',label:'🧀 Extra Cheese',price:2.00},{key:'add_egg',label:'🍳 Fried Egg',price:2.00},{key:'add_sauce',label:'🥫 Extra Sauce',price:0.50}] },
    'PRD049': { spice:false, remove:[{key:'no_sauce',label:'🥫 No Tartar Sauce'},{key:'no_lemon',label:'🍋 No Lemon'}], addons:[{key:'add_sauce',label:'🥫 Extra Tartar Sauce',price:1.00},{key:'add_extra',label:'➕ Extra Chips',price:3.00}] },
    'PRD050': { spice:false, remove:[], addons:[], dessert:true },
    'PRD051': { spice:false, remove:[], addons:[], dessert:true },
    'PRD052': { spice:false, remove:[], addons:[], dessert:true },
    'PRD053': { spice:false, remove:[], addons:[], dessert:true },
    'PRD054': { spice:false, dessert:true, remove:[], addons:[{key:'add_cream',label:'🍦 Add Ice Cream',price:3.00}] },
    'PRD055': { spice:false, dessert:true, remove:[], addons:[{key:'add_cream',label:'🍦 Add Ice Cream',price:3.00}] },
    'PRD056': { spice:false, remove:[], addons:[], beverage:true },
    'PRD057': { spice:false, remove:[], addons:[], beverage:true },
    'PRD058': { spice:false, beverage:true, remove:[], addons:[{key:'add_sugar',label:'🍬 Extra Sugar',price:0.00},{key:'add_milk',label:'🥛 Extra Milk',price:0.50}] },
    'PRD059': { spice:false, beverage:true, remove:[], addons:[{key:'add_sugar',label:'🍬 Extra Sugar',price:0.00},{key:'add_shot',label:'☕ Extra Shot',price:2.00}] },
    'PRD060': { spice:false, beverage:true, remove:[{key:'no_lemon',label:'🍋 Less Lemon'}], addons:[{key:'add_sugar',label:'🍬 Extra Sugar',price:0.00}] },
    'PRD061': { spice:false, remove:[], addons:[], beverage:true },
    'PRD062': { spice:false, remove:[], addons:[], beverage:true },
    'PRD063': { spice:false, beverage:true, remove:[], addons:[{key:'add_cream',label:'🍦 Extra Whipped Cream',price:1.00}] },
};

function _fetchOpts(productId) {
    if (_optsCache[productId] !== undefined) return Promise.resolve(_optsCache[productId]);
    return fetch('get_product_options.php?pid=' + encodeURIComponent(productId))
        .then(r => r.json())
        .then(data => {
            if (data.success && data.options && typeof data.options === 'object') {
                _optsCache[productId] = data.options;
            } else if (LEGACY_OPTIONS[productId]) {
                _optsCache[productId] = LEGACY_OPTIONS[productId];
            } else {
                _optsCache[productId] = null;
            }
            return _optsCache[productId];
        })
        .catch(() => {
            _optsCache[productId] = LEGACY_OPTIONS[productId] || null;
            return _optsCache[productId];
        });
}

function _currentOpts() {
    const cached = _optsCache[_modalItem.productId];
    if (cached === null || cached === undefined) return _EMPTY_OPTS;
    return cached;
}

function _defaultInstance(opts) {
    const inst = { spice: opts.spice ? 'original' : null, remove: {}, addons: {}, serve: 'after_meal', temp: 'ice' };
    if (opts.remove) opts.remove.forEach(r => inst.remove[r.key] = false);
    if (opts.addons) opts.addons.forEach(a => inst.addons[a.key] = false);
    return inst;
}

function _syncInstances() {
    const opts = _currentOpts();
    while (_modalInstances.length < _modalQty) _modalInstances.push(_defaultInstance(opts));
    _modalInstances = _modalInstances.slice(0, _modalQty);
}

function renderModalInstances() {
    _syncInstances();
    const opts     = _currentOpts();
    const isDesert = !!opts.dessert;
    const isBev    = !!opts.beverage;
    const hasSpice  = !isDesert && !isBev && !!opts.spice;
    const hasRemove = opts.remove && opts.remove.length > 0;
    const hasAddons = opts.addons && opts.addons.length > 0;
    const multi     = _modalQty > 1;
    const noOptions = !hasSpice && !hasRemove && !hasAddons && !isDesert && !isBev;

    let html = '';
    for (let i = 0; i < _modalQty; i++) {
        const s = _modalInstances[i];
        html += `<div class="cm-instance-block">`;
        if (multi) {
            html += `<div class="cm-instance-label"><div class="cm-instance-badge">${i+1}</div>Item ${i+1} of ${_modalQty}</div>`;
        }
        if (noOptions) {
            html += `<div class="cm-no-options">No customization options for this item.</div></div>`;
            continue;
        }
        if (hasSpice) {
            html += `<div class="cm-section"><p class="cm-section-title">🌶 Spice Level</p><div style="display:flex;gap:8px;">`;
            [['original','Original'],['mild','🌶 Mild'],['spicy','🌶🌶 Spicy']].forEach(([val,label]) => {
                const active = s.spice === val;
                html += `<button class="cm-spice-opt${active?' cm-spice-active':''}" data-idx="${i}" data-val="${val}" onclick="cmSelectSpice(this)" style="flex:1;padding:9px 4px;border-radius:10px;border:2px solid ${active?'#E87D0D':'#e0e0e0'};background:${active?'#E87D0D':'white'};color:${active?'white':'#555'};font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;">${label}</button>`;
            });
            html += `</div></div>`;
        }
        if (hasRemove && !isDesert && !isBev) html += _renderRemoves(opts.remove, s, i, hasSpice ? '12px' : '0');
        if (hasAddons && !isDesert && !isBev) html += _renderAddons(opts.addons, s, i);
        if (isDesert) {
            html += `<div class="cm-section"><p class="cm-section-title">🍽 Serving Time</p><div style="display:flex;gap:8px;">`;
            [['before_meal','Before Meal'],['after_meal','After Meal']].forEach(([val,label]) => {
                const active = s.serve === val;
                html += `<button class="cm-serve-opt${active?' cm-serve-active':''}" data-idx="${i}" data-val="${val}" onclick="cmSelectServe(this)" style="flex:1;padding:9px 4px;border-radius:10px;border:2px solid ${active?'#E87D0D':'#e0e0e0'};background:${active?'#E87D0D':'white'};color:${active?'white':'#555'};font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;">${label}</button>`;
            });
            html += `</div></div>`;
            if (hasAddons) html += _renderAddons(opts.addons, s, i);
            if (hasRemove) html += _renderRemoves(opts.remove, s, i, '12px');
        }
        if (isBev) {
            html += `<div class="cm-section"><p class="cm-section-title">🧋 Temperature</p><div style="display:flex;gap:6px;flex-wrap:wrap;">`;
            [['ice','❄️ Ice'],['less_ice','Less Ice'],['no_ice','No Ice'],['hot','🔥 Hot']].forEach(([val,label]) => {
                const active = s.temp === val;
                html += `<button class="cm-temp-opt${active?' cm-temp-active':''}" data-idx="${i}" data-val="${val}" onclick="cmSelectTemp(this)" style="flex:1;min-width:70px;padding:9px 4px;border-radius:10px;border:2px solid ${active?'#E87D0D':'#e0e0e0'};background:${active?'#E87D0D':'white'};color:${active?'white':'#555'};font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;">${label}</button>`;
            });
            html += `</div></div>`;
            if (hasRemove) html += _renderRemoves(opts.remove, s, i, '12px');
            if (hasAddons) html += _renderAddons(opts.addons, s, i);
        }
        html += `</div>`;
    }
    document.getElementById('modalInstancesContainer').innerHTML = html;
    updateModalTotal();
}

function _renderRemoves(removes, s, i, marginTop) {
    let html = `<div class="cm-section" style="margin-top:${marginTop}"><p class="cm-section-title">🚫 Remove Items <span style="color:#aaa;font-weight:400;">(optional)</span></p><div style="display:flex;flex-wrap:wrap;gap:8px;">`;
    removes.forEach(r => {
        const active = s.remove[r.key];
        html += `<button class="cm-remove-opt${active?' cm-remove-active':''}" data-idx="${i}" data-val="${r.key}" onclick="cmToggleRemove(this)" style="padding:7px 13px;border-radius:20px;border:2px solid ${active?'#E87D0D':'#e0e0e0'};background:${active?'#fff0e0':'white'};color:${active?'#E87D0D':'#555'};font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;">${r.label}</button>`;
    });
    html += `</div></div>`;
    return html;
}

function _renderAddons(addons, s, i) {
    let html = `<div class="cm-section" style="margin-top:12px;padding-bottom:4px;"><p class="cm-section-title">➕ Add-ons <span style="color:#E87D0D;font-weight:400;">(optional)</span></p>`;
    addons.forEach(a => {
        const active = s.addons[a.key];
        const priceLabel = a.price > 0 ? `+RM ${parseFloat(a.price).toFixed(2)}` : 'Free';
        const emoji = a.label.split(' ')[0];
        const labelText = a.label.split(' ').slice(1).join(' ');
        html += `<div class="cm-addon-row${active?' cm-addon-active':''}" data-idx="${i}" data-key="${a.key}" data-price="${a.price}" onclick="cmToggleAddon(this)" style="display:flex;align-items:center;justify-content:space-between;padding:11px 13px;border-radius:12px;margin-bottom:7px;cursor:pointer;border:1.5px solid ${active?'#E87D0D':'#e8e8e8'};background:${active?'#fff8f0':'white'};transition:all .15s;user-select:none;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:18px;">${emoji}</span>
                <p style="margin:0;font-size:13px;font-weight:600;color:#222;">${labelText}</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;color:#E87D0D;font-weight:600;">${priceLabel}</span>
                <div class="cm-addon-cb" style="width:22px;height:22px;border-radius:6px;border:2px solid ${active?'#E87D0D':'#ddd'};background:${active?'#E87D0D':'white'};display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0;">
                    ${active?'<svg width="12" height="12" viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3" stroke="white" stroke-width="2" fill="none" stroke-linecap="round"/></svg>':''}
                </div>
            </div>
        </div>`;
    });
    html += `</div>`;
    return html;
}

function cmSelectSpice(btn) { _modalInstances[parseInt(btn.dataset.idx)].spice = btn.dataset.val; renderModalInstances(); }
function cmToggleRemove(btn) { const idx=parseInt(btn.dataset.idx); _modalInstances[idx].remove[btn.dataset.val]=!_modalInstances[idx].remove[btn.dataset.val]; renderModalInstances(); }
function cmToggleAddon(row)  { const idx=parseInt(row.dataset.idx); _modalInstances[idx].addons[row.dataset.key]=!_modalInstances[idx].addons[row.dataset.key]; renderModalInstances(); }
function cmSelectServe(btn)  { _modalInstances[parseInt(btn.dataset.idx)].serve = btn.dataset.val; renderModalInstances(); }
function cmSelectTemp(btn)   { _modalInstances[parseInt(btn.dataset.idx)].temp  = btn.dataset.val; renderModalInstances(); }

function changeModalQty(delta) {
    _modalQty = Math.min(_modalRemaining, Math.max(1, _modalQty + delta));
    _syncInstances();
    document.getElementById('modalQtyDisplay').textContent = _modalQty;
    document.getElementById('modalQtyMinus').disabled = (_modalQty === 1);
    document.getElementById('modalQtyPlus').disabled  = (_modalQty >= _modalRemaining);
    renderModalInstances();
}

function updateModalTotal() {
    const opts = _currentOpts();
    let total = 0;
    _modalInstances.forEach(s => {
        let extra = 0;
        if (opts.addons) opts.addons.forEach(a => { if (s.addons[a.key]) extra += parseFloat(a.price) || 0; });
        total += _modalItem.price + extra;
    });
    document.getElementById('modalTotalPrice').textContent = 'RM ' + total.toFixed(2);
}

function refreshProductQtyButtons() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const cartQty = {};
    cart.forEach(item => { cartQty[item.id] = (cartQty[item.id] || 0) + (parseInt(item.quantity) || 1); });

    document.querySelectorAll('.product-card').forEach(card => {
        if (card.classList.contains('out-of-stock')) return;
        let pid = null;
        card.querySelectorAll('[onclick]').forEach(b => {
            const m = (b.getAttribute('onclick') || '').match(/'([A-Z0-9]+)'/);
            if (m) pid = m[1];
        });
        if (!pid) return;

        const qtyInCart = cartQty[pid] || 0;
        const remaining = Math.max(0, 50 - qtyInCart);
        const qtySpan  = card.querySelector('.qty-value, .qty-display');
        const plusBtn  = card.querySelector('.qty-plus') || card.querySelector('.qty-btn:last-of-type');
        const minusBtn = card.querySelector('.qty-minus') || card.querySelector('.qty-btn:first-of-type');
        const addBtns  = card.querySelectorAll('.add-to-cart-btn, .btn-small');
        const existingBadge = card.querySelector('.max-qty-badge');

        if (remaining === 0) {
            if (plusBtn)  { plusBtn.disabled = true; plusBtn.title = 'Max 50 reached'; }
            if (minusBtn) { minusBtn.disabled = true; }
            if (qtySpan)  { qtySpan.textContent = '0'; }
            addBtns.forEach(b => { b.disabled = true; b.style.opacity = '0.4'; b.style.cursor = 'not-allowed'; b.style.pointerEvents = 'none'; });
            if (!existingBadge) {
                const badge = document.createElement('div');
                badge.className = 'max-qty-badge';
                badge.innerHTML = '🔒 Max 50 in cart';
                badge.style.cssText = 'font-size:11px;font-weight:700;color:#dc2626;background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.2);border-radius:20px;padding:3px 10px;margin-bottom:8px;display:inline-flex;align-items:center;gap:4px;';
                const infoDiv = card.querySelector('.product-info');
                const qtyControls = infoDiv ? infoDiv.querySelector('.quantity-controls') : null;
                if (qtyControls) infoDiv.insertBefore(badge, qtyControls);
                else if (infoDiv) infoDiv.prepend(badge);
            }
        } else {
            if (existingBadge) existingBadge.remove();
            addBtns.forEach(b => { b.disabled = false; b.style.opacity = ''; b.style.cursor = ''; b.style.pointerEvents = ''; });
            if (qtySpan) {
                const currentDisplayed = parseInt(qtySpan.textContent) || 1;
                const newDisplay = Math.min(currentDisplayed > remaining ? 1 : currentDisplayed, remaining);
                qtySpan.textContent = newDisplay;
                if (minusBtn) minusBtn.disabled = (newDisplay <= 1);
            }
            if (plusBtn && qtySpan) plusBtn.disabled = (parseInt(qtySpan.textContent) >= remaining);
        }
    });
}

function openCustomizeModal(id, name, price, initialQty, category) {
    category = (category || '').toLowerCase();
    _modalItem = { id, name, price: parseFloat(price), category, productId: id };

    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const cartQtyForItem = cart.filter(i => i.id == id).reduce((sum, i) => sum + (parseInt(i.quantity) || 1), 0);
    const remaining = Math.max(0, 50 - cartQtyForItem);

    if (remaining === 0) { showCartToast('⚠️ Maximum 50 for "' + name + '" already in cart!'); return; }

    _modalRemaining = remaining;
    _modalQty = Math.min(remaining, Math.max(1, parseInt(initialQty) || 1));
    _modalInstances = [];

    document.getElementById('modalItemName').textContent   = name;
    document.getElementById('modalItemPrice').textContent  = 'RM ' + parseFloat(price).toFixed(2);
    document.getElementById('modalQtyDisplay').textContent = _modalQty;
    document.getElementById('modalQtyMinus').disabled      = (_modalQty === 1);
    document.getElementById('modalQtyPlus').disabled       = (_modalQty >= remaining);
    document.getElementById('modalInstancesContainer').innerHTML = '';

    const overlay = document.getElementById('customizeOverlay');
    const modal   = document.getElementById('customizeModal');
    const loading = document.getElementById('modalLoadingState');
    overlay.style.display = 'flex';
    requestAnimationFrame(() => { modal.style.transform = 'scale(1)'; modal.style.opacity = '1'; });
    overlay.onclick = e => { if (e.target === overlay) closeCustomizeModal(); };

    const alreadyCached = _optsCache[id] !== undefined;
    if (!alreadyCached) {
        loading.style.display = 'flex';
        document.getElementById('modalInstancesContainer').style.display = 'none';
    }

    _fetchOpts(id).then(() => {
        loading.style.display = 'none';
        document.getElementById('modalInstancesContainer').style.display = '';
        _syncInstances();
        renderModalInstances();
    });
}

function closeCustomizeModal() {
    const modal   = document.getElementById('customizeModal');
    const overlay = document.getElementById('customizeOverlay');
    modal.style.transform = 'scale(0.92)';
    modal.style.opacity   = '0';
    setTimeout(() => { overlay.style.display = 'none'; }, 250);
}

function confirmAddToCart() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const opts = _currentOpts();

    const cartQtyForItem = cart.filter(i => i.id == _modalItem.id).reduce((sum, i) => sum + (parseInt(i.quantity) || 1), 0);
    const remaining = Math.max(0, 50 - cartQtyForItem);

    if (remaining === 0) { showCartToast('⚠️ Maximum 50 for "' + _modalItem.name + '" already in cart!'); closeCustomizeModal(); return; }
    if (_modalQty > remaining) { _modalQty = remaining; _modalInstances = _modalInstances.slice(0, _modalQty); }

    const groups = {};
    _modalInstances.forEach(s => {
        const removes = {};
        if (opts.remove) opts.remove.forEach(r => { removes[r.key] = s.remove[r.key] || false; });
        const addons = {};
        let addonTotal = 0;
        if (opts.addons) { opts.addons.forEach(a => { addons[a.key] = s.addons[a.key] || false; if (s.addons[a.key]) addonTotal += parseFloat(a.price) || 0; }); }

        const sig = JSON.stringify({ spice: s.spice || null, serve: opts.dessert ? s.serve : null, temp: opts.beverage ? s.temp : null, removes, addons });

        if (!groups[sig]) {
            groups[sig] = { newItem: { id: _modalItem.id, name: _modalItem.name, price: _modalItem.price + addonTotal, basePrice: _modalItem.price, quantity: 0, category: _modalItem.category, spice_level: s.spice || null, serve_time: opts.dessert ? s.serve : null, bev_temp: opts.beverage ? s.temp : null, removes, addons }, count: 0 };
        }
        groups[sig].count++;
    });

    Object.values(groups).forEach(({ newItem, count }) => {
        const existingIndex = cart.findIndex(i =>
            i.id == newItem.id && i.spice_level === newItem.spice_level &&
            i.serve_time === newItem.serve_time && i.bev_temp === newItem.bev_temp &&
            JSON.stringify(i.removes) === JSON.stringify(newItem.removes) &&
            JSON.stringify(i.addons)  === JSON.stringify(newItem.addons)
        );
        if (existingIndex >= 0) { cart[existingIndex].quantity = (parseInt(cart[existingIndex].quantity) || 0) + count; }
        else { newItem.quantity = count; cart.push(newItem); }
    });

    localStorage.setItem('cart', JSON.stringify(cart));
    localStorage.setItem('cartLastModified', Date.now().toString());

    if (typeof updateCartBadge === 'function') updateCartBadge();
    refreshProductQtyButtons();
    closeCustomizeModal();
    showCartToast(_modalItem.name + ' added to cart!');
}

function showCartToast(msg) {
    const existing = document.querySelector('.ft-toast');
    if (existing) existing.remove();
    const t = document.createElement('div');
    t.className = 'ft-toast';
    t.innerHTML = '<i class="fas fa-check-circle" style="margin-right:8px;"></i>' + msg;
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#E87D0D;color:white;padding:13px 20px;border-radius:10px;z-index:99999;font-size:14px;font-weight:600;box-shadow:0 4px 16px rgba(232,125,13,0.35);transition:opacity 0.3s;';
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2200);
}

document.addEventListener('DOMContentLoaded', refreshProductQtyButtons);
document.addEventListener('visibilitychange', function() { if (document.visibilityState === 'visible') refreshProductQtyButtons(); });
window.addEventListener('pageshow', function(e) { if (e.persisted) refreshProductQtyButtons(); });
</script>