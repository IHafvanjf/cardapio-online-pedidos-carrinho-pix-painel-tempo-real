<?php
require __DIR__.'/auth.php';            // protege a página
$user = $_SESSION['user'] ?? 'Admin';   // pega o nome salvo no login
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>BurgerHub • Painel</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <header class="admin-header">
    <div class="brand">
      <i class="fa-solid fa-burger"></i>
      <div>
        <strong>BurgerHub</strong>
        <small>Painel de Controle</small>
      </div>
    </div>

    <div class="header-actions">
      <div class="search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input id="globalSearch" type="search" placeholder="Buscar produtos, extras, ingredientes..." />
      </div>

      <button id="btnNewProduct" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Novo produto
      </button>

      <!-- === usuário logado / menu === -->
      <!-- === usuário logado / menu === -->
<div class="user-menu">
  <button id="profileBtn" class="user-btn" aria-haspopup="menu" aria-expanded="false">
    <span class="avatar" aria-hidden="true">A</span>
    <span class="u-name">admin</span>
    <i class="fa-solid fa-chevron-down chevron"></i>
  </button>

  <div id="profileMenu" class="user-dropdown" role="menu" aria-hidden="true">
    <div class="ud-head">
      <span class="avatar sm">A</span>
      <div>
        <strong>admin</strong>
        <small class="muted">Administrador</small>
      </div>
    </div>
    <a class="ud-item" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
  </div>
</div>
<!-- === fim usuário === -->

      <!-- === fim usuário === -->
    </div>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <nav>
        <button class="nav-item active" data-panel="produtos"><i class="fa-solid fa-boxes-stacked"></i> Produtos</button>
        <button class="nav-item" data-panel="ingredientes"><i class="fa-solid fa-carrot"></i> Ingredientes</button>
        <button class="nav-item" data-panel="extras"><i class="fa-solid fa-bacon"></i> Extras</button>
        <button class="nav-item" data-panel="precos"><i class="fa-solid fa-tag"></i> Preços</button>
      </nav>

  
    </aside>

    <main class="content">
      <!-- Produtos -->
      <section id="panel-produtos" class="panel active">
        <div class="panel-head">
          <h2>Produtos</h2>
          <div class="tabs" id="productTabs">
            <button class="tab active" data-cat="hamburgueres"><i class="fa-solid fa-burger"></i> Hambúrgueres</button>
            <button class="tab" data-cat="combos"><i class="fa-solid fa-bowl-food"></i> Combos</button>
            <button class="tab" data-cat="bebidas"><i class="fa-solid fa-glass-water"></i> Bebidas</button>
            <button class="tab" data-cat="sobremesas"><i class="fa-regular fa-ice-cream"></i> Sobremesas</button>
          </div>
        </div>

        <div class="bulk-actions">
          <button class="btn" id="btnIncrease5"><i class="fa-solid fa-arrow-trend-up"></i> +5% na categoria</button>
          <button class="btn" id="btnDecrease5"><i class="fa-solid fa-arrow-trend-down"></i> -5% na categoria</button>
          <button class="btn" id="btnNewProduct2"><i class="fa-solid fa-plus"></i> Novo</button>
        </div>

        <div id="productGrid" class="grid"></div>
      </section>

      <!-- Ingredientes -->
      <section id="panel-ingredientes" class="panel">
        <div class="panel-head">
          <h2>Ingredientes</h2>
          <div class="inline">
            <div class="search small">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input id="ingredientsSearch" type="search" placeholder="Buscar ingrediente..." />
            </div>
            <button id="btnNewIngredient" class="btn"><i class="fa-solid fa-plus"></i> Novo ingrediente</button>
          </div>
        </div>
        <div id="ingredientsList" class="list"></div>
        <small class="muted">Marque como “Indisponível” para refletir nos produtos que usam o ingrediente.</small>
      </section>

      <!-- Extras -->
      <section id="panel-extras" class="panel">
        <div class="panel-head">
          <h2>Extras</h2>
          <div class="inline">
            <div class="search small">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input id="extrasSearch" type="search" placeholder="Buscar extra..." />
            </div>
            <button id="btnNewExtra" class="btn"><i class="fa-solid fa-plus"></i> Novo extra</button>
          </div>
        </div>
        <div id="extrasList" class="list"></div>
        <small class="muted">Extras podem ter acréscimo de preço e disponibilidade própria.</small>
      </section>

      <!-- Preços -->
      <section id="panel-precos" class="panel">
        <div class="panel-head">
          <h2>Ajuste de preços</h2>
        </div>
        <div class="price-tools">
          <div class="tool">
            <label>Categoria</label>
            <select id="bulkCategory">
              <option value="hamburgueres">Hambúrgueres</option>
              <option value="combos">Combos</option>
              <option value="bebidas">Bebidas</option>
              <option value="sobremesas">Sobremesas</option>
              <option value="todas">Todas</option>
            </select>
          </div>
          <div class="tool">
            <label>Percentual</label>
            <div class="pct">
              <input id="bulkPercent" type="number" value="10" step="0.5" />
              <span>%</span>
            </div>
          </div>
          <div class="tool">
            <button id="btnApplyBulk" class="btn btn-primary"><i class="fa-solid fa-check"></i> Aplicar ajuste</button>
          </div>
        </div>
        <div class="note muted">Ex.: 10 aplica +10%. Para redução, use valores negativos (ex.: -5).</div>
      </section>

      <!-- Importar/Exportar -->
      <section id="panel-exportar" class="panel">
        <div class="panel-head">
          <h2>Importar/Exportar</h2>
        </div>
        <div class="exporter">
          <button id="btnExport" class="btn"><i class="fa-solid fa-download"></i> Exportar JSON</button>
          <label for="importFile" class="btn"><i class="fa-solid fa-upload"></i> Importar JSON</label>
          <input id="importFile" type="file" accept="application/json" hidden />
        </div>
        <pre id="exportPreview" class="export-preview"></pre>
      </section>
    </main>
  </div>

  <!-- Modal Produto -->
  <div class="modal" id="productModal" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3 id="productModalTitle">Novo produto</h3>
        <button class="icon-btn" data-close="#productModal" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <form id="productForm" class="form">
          <input type="hidden" id="prodId" />
          <div class="form-grid">
            <div class="img-col">
              <label class="dropzone" id="imgDrop">
                <input type="file" id="prodImg" accept="image/*" hidden />
                <img id="imgPreview" alt="Prévia" />
                <span class="dz-text"><i class="fa-solid fa-image"></i> Arraste a imagem ou clique</span>
              </label>
            </div>
            
            <div class="fields-col">
              <label>Nome
                <input id="prodName" type="text" required />
              </label>
              <label>Categoria
                <select id="prodCat" required>
                  <option value="hamburgueres">Hambúrgueres</option>
                  <option value="combos">Combos</option>
                  <option value="bebidas">Bebidas</option>
                  <option value="sobremesas">Sobremesas</option>
                </select>
              </label>
              <div class="form-group">
  <label for="prep_time_min">Tempo de preparo (min)</label>
  <input type="number" id="prodPrep" name="prep_time_min" class="form-control" min="1" required>
</div>

              <label>Preço (R$)
                <input id="prodPrice" type="number" min="0" step="0.01" required />
              </label>
              <label>Descrição
                <textarea id="prodDesc" rows="3" placeholder="Escreva uma descrição curta..."></textarea>
              </label>
              <div class="row">
                <label class="switch">
                  <input id="prodActive" type="checkbox" checked />
                  <span></span> Disponível para venda
                </label>
              </div>
            </div>
          </div>

        <div class="form-grid two">
          <div>
            <label>Ingredientes do produto</label>

            <div class="picker">
              <div class="picker-head">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="ingSearch" type="search" placeholder="Buscar ingrediente..." />
              </div>
              <div id="prodIngs" class="picker-list"></div>
              <div class="picker-selected" id="prodIngsSelected"></div>
            </div>

          </div>
          
          <div>
            <label>Extras disponíveis</label>

            <div class="picker">
              <div class="picker-head">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="extraSearch" type="search" placeholder="Buscar extra..." />
              </div>
              <div id="prodExtras" class="picker-list"></div>
              <div class="picker-selected" id="prodExtrasSelected"></div>
            </div>
            <!-- Itens do Combo (aparece só quando categoria = combos) -->
          <div id="sec-combo" class="combo-only hidden">
            <label>Itens do combo</label>

            <div class="picker">
              <div class="picker-head">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="comboSearch" type="search" placeholder="Buscar item (hambúrguer, bebida, sobremesa)..." />
              </div>
              <div id="comboItems" class="picker-list"></div>
              <div class="picker-selected" id="comboSelected"></div>
            </div>

            <small class="muted">
              Dica: escolha os produtos que compõem o combo. O preço do combo é definido no campo “Preço”, não é somado automaticamente.
            </small>
          </div>


          </div>
        </div>

        </form>
      </div>
      <div class="modal-footer">
        <button class="btn" data-close="#productModal"><i class="fa-regular fa-circle-xmark"></i> Cancelar</button>
        <button class="btn btn-primary" id="btnSaveProduct"><i class="fa-solid fa-check"></i> Salvar produto</button>
      </div>
    </div>
  </div>


  <!-- Modal Extra -->
  <div class="modal" id="extraModal" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3 id="extraModalTitle">Novo extra</h3>
        <button class="icon-btn" data-close="#extraModal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="form two">
          <label>Nome
            <input id="extraName" type="text" />
          </label>
          <label>Preço adicional (R$)
            <input id="extraPrice" type="number" step="0.01" min="0" value="0" />
          </label>
          <label class="switch">
            <input id="extraActive" type="checkbox" checked />
            <span></span> Disponível
          </label>
          <input type="hidden" id="extraId" />
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" data-close="#extraModal"><i class="fa-regular fa-circle-xmark"></i> Cancelar</button>
        <button class="btn btn-primary" id="btnSaveExtra"><i class="fa-solid fa-check"></i> Salvar extra</button>
      </div>
    </div>
  </div>

  <!-- Modal Ingrediente -->
  <div class="modal" id="ingModal" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3 id="ingModalTitle">Novo ingrediente</h3>
        <button class="icon-btn" data-close="#ingModal"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="form two">
          <label>Nome
            <input id="ingName" type="text" />
          </label>
          <label class="switch">
            <input id="ingActive" type="checkbox" checked />
            <span></span> Disponível
          </label>
          <input type="hidden" id="ingId" />
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" data-close="#ingModal"><i class="fa-regular fa-circle-xmark"></i> Cancelar</button>
        <button class="btn btn-primary" id="btnSaveIng"><i class="fa-solid fa-check"></i> Salvar ingrediente</button>
      </div>
    </div>
  </div>

  <div id="toast" class="toast"></div>

  <script src="admin.js" defer></script>

</body>
</html>
