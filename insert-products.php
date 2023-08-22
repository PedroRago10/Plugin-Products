<?php
require_once plugin_dir_path( __FILE__ ) . 'lib/simple_html_dom.php';

/*
Plugin Name: Insert Products
Description: The purpose of this plugin is to perform a scrape to obtain products and their prices.
Version: 1.0
Author: Pedro Rago
*/

// Evite acessar diretamente este arquivo
if (!defined('ABSPATH')) {
    exit;
}

class InsertProductsPlugin
{
    private $url = "https://product-pages-v2-bff.prod.merch.vpsvc.com/v1/compatibility-pricing/vistaprint/en-CA/";
    private $url_product = "https://upload-flow-options-cdn.prod.merch.vpsvc.com/v6/product-options/vistaprint/en-CA/";

    public function __construct()
    {
        add_action('admin_menu', array($this, 'insert_products_admin_page'));
        add_action('admin_enqueue_scripts', array($this, 'insert_products_enqueue_scripts'));
        add_action('wp_ajax_products_get_options', array($this, 'products_get_options'));
        add_action('wp_ajax_nopriv_products_get_options', array($this, 'products_get_options'));
        add_action('wp_ajax_insert_products_submit_form', array($this, 'insert_products_submit_form'));
        add_action('wp_ajax_nopriv_insert_products_submit_form', array($this, 'insert_products_submit_form'));
        add_action('wp_ajax_call_variations', array($this, 'call_variations'));
        add_action('wp_ajax_nopriv_call_variations', array($this, 'call_variations'));
    }

    public function insert_products_admin_page()
    {
        add_menu_page(
            'Scraping Products',
            'Scraping Products',
            'manage_options',
            'insert-products-page',
            array($this, 'insert_products_render_page')
        );
    }

    public function insert_products_render_page()
    {
        ?>
        <link rel="stylesheet" href="<?php echo plugins_url('assets/app.css', __FILE__); ?>">

        <div class="wrap" style='position: relative'>
            <h1>Scraping Products</h1>
            <div class='loading'>
                <div class='container'>
                    <h2 class='title-loading' style='margin-top: -8em'>We are performing data scraping for the <strong></strong> product...</h2>
                    <legend>This may take a while due to the number of product variations but rest assured, all data will be saved correctly until the end of this process and you can export it in CSV format. </legend>
                    <ul class='feedback'>
                        <li class='feedback-product'></li>
                        <li class='feedback-variations'><p class='feedback-variations-label'></p> <b>...</b></li>
                    </ul>
                    <img src="<?php echo plugin_dir_path(__FILE__) . 'loading_search.svg'?>"  style='max-width: 7em;margin-top: 0.5em;' alt="">
                </div>
            </div>
            <form id="insert-products-form" style='background-color: #fff;padding: 2em 3em;border-radius: 7px;border-bottom: 7px solid #2271b1;'>
                <!-- Adicione aqui os campos do formulário -->
                <div class="container mt-4">
                    <h4 style='margin-top: 0; margin-top: 0;font-size: 1.4em;margin-bottom: 1.5em;'>Select a category:</h4>
                    <div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="categories" id="business-cards" value="business-cards">
                            <label class="form-check-label" for="business-cards">Business Cards</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="categories" id="postcards" value="postcards">
                            <label class="form-check-label" for="postcards">Postcards & Print Advertising</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="categories" id="signs" value="signs">
                            <label class="form-check-label" for="signs">Signs, Banners & Posters</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="categories" id="labels" value="labels">
                            <label class="form-check-label" for="labels">Labels, Stickers & Packaging</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="categories" id="home-gifts" value="home-gifts">
                            <label class="form-check-label" for="home-gifts">Home & Gifts</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="categories" id="wedding" value="wedding">
                            <label class="form-check-label" for="wedding">Wedding</label>
                        </div>
                    </div>
                </div>

                <div class='products' style='display: none; margin-top: 2em;margin-bottom: 2em;'>
                    <div style='display: flex; flex-direction: column;'>
                        <h4 style='margin-top: 0; margin-top: 0;font-size: 1.4em;margin-bottom: 1.5em;'>Select a product:</h4>
                        <?php
                        // Lê o conteúdo do arquivo JSON
                        $products = plugin_dir_path(__FILE__) . 'conf.json';

                        // Decodifica o JSON em um array associativo
                        $data = json_decode($products, true);

                        // Loop pelos grupos de produtos
                        foreach ($data['products'] as $category => $products) {
                            echo '<label>' . $category . ':</label>';
                            echo '<select name="' . $category . '">';
                        
                            // Loop pelos produtos dentro do grupo
                            foreach ($products as $product) {
                                echo '<option value="' . $product['value'] . '">' . $product['text'] . '</option>';
                            }
                        
                            echo '</select><br>';
                        }
                        ?>
                    </div>
                </div>
                <div class='quantities' style='display: none; margin-bottom: 2em'>
                    <div class='options-selects' style='display: flex; flex-direction: column'>
                        <h4 style='margin-top: 0; margin-top: 0;font-size: 1.4em;margin-bottom: 1.5em;'>Quantities:</h4>
                        <select name="quantities" id="quantities" style='padding: 0.3em 1em' required>
                            
                        </select>
                    </div>
                </div>
                
                <div class='options' style='display: none; margin-bottom: 3em;'>
                    <h4 style='margin-top: 0; margin-top: 0;font-size: 1.4em;margin-bottom: 1.5em;'>Select the options you prefer:</h4>
                    <div class='options-selects' style='display: flex;  '>
                        
                    </div>
                </div>
                
                <div class='col-md-2' >
                    <button type='button' class='button button-primary btnSubmitScrap disabled' disabled style='margin-top: 1em'>Search by product</button>
                    <a href="" style='display: none; margin-left: 0.5em; margin-top: 1em;' class='button button-success btnDownload' download>Download Product CSV</a>
                </div>
            </div>
        <?php
    }

    public function insert_products_enqueue_scripts()
    {
        wp_enqueue_script('insert-products-script', plugin_dir_url(__FILE__) . 'insert-products.js', array('jquery'), '1.0', true);
    }

    public function insert_products_submit_form()
    {
        // Processar o formulário aqui
        $version = urlencode($_POST['version']);
        $quantities = $_POST['quantities'];
        $name = $_POST['name'];
        $product = $_POST['product'];
        $product = explode("=", $product)[0];
        $sku = $product;
        $product = urlencode($product);
        $categories = $_POST['categories'];
        
        $categories = ucfirst(str_replace("-", " ", $categories));
        $optionPost = $_POST['option'];
        
        if ($optionPost) {
            $selected = json_encode(array_filter($optionPost));
            $selected = urlencode($selected);
            $options = $_GET['options'];
            $options = $_GET['options'];
            $options = array_keys($optionPost);
            $options = urlencode(json_encode($options));
        } else {
            $options = "%5B%5D";
            $selected = "%7B%7D";
        }
    
        $url = $this->url . $product . "?selections=" . $selected . "&productPageOptions=" . $options . "&quantities=%5B%5D&version=" . $version . "&applyProductConstraints=true&mpvId=&requestor=product-page-v2&currentQuantity=" . $quantities . "&optimizelyEndUserId=_0f681020-8d54-4dc2-ad63-784cacd27c51";
        
        $array = $this->requestProduct($url);
    
        // Verificar se houve algum erro na decodificação do JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erro ao decodificar o JSON: ' . json_last_error_msg());
        }
    
        $caminho_arquivo = $this->generateCSV($array, $sku, $name, $categories, $quantities, $product, $options, $version);
    
        // Se necessário, retornar uma resposta em JSON para o AJAX
        $response = array(
            'status' => 'success',
            'message' => 'Formulário enviado com sucesso!',
            'file_url' => $caminho_arquivo,
            'data' => $array,
        );
        wp_send_json_success($response);
    }
    
    public function call_variations()
    {
        $name = $_GET['name'];
        $product = $_GET['product'];
        $version = urlencode($_GET['version']);
        $quantities = $_GET['quantities'];
        $categories = $_GET['categories'];
        $position = 1;
        $document = $_GET['document'];
    
        $product = explode("=", $product)[0];
    
        $sku = $product;
    
        $document = str_replace("www.monarktek.com/wp-content/plugins/insert-products/", "", $document);
    
        $url = $this->url . $product . "?pricingContext=eyJtZXJjaGFudElkIjoiVklTVEFQUklOVCIsIm1hcmtldCI6IkNBIiwiY3VzdG9tZXJHcm91cHMiOlsic2l0ZV90YWdnaW5g5nX25ld19jdXN0b21lciIsIm5ld191c2VyX2hlcm8iXX0&quantity=" . $quantities . "&productVersion=" . $version . "&requestor=UploadFlow&optimizelyEndUserId=_0f681020-8d54-4dc2-ad63-784cacd27c51";
        $variation = $this->requestProduct($url);
    
        $csvFilePath = plugin_dir_path(__FILE__) . $document;
    
        $csvContent = file_get_contents($csvFilePath);
    
        if ($csvContent === false) {
            return false; // Não foi possível obter o conteúdo do arquivo
        }
    
        $fileHandle = fopen($csvFilePath, 'a'); // Abre o arquivo para escrita (append)
    
        foreach ($variation['options'] as $variationOption) {
            $priceUntaxed = $variationOption['price']['totalListPrice']['untaxed'];
            $priceTaxed = $variationOption['price']['totalListPrice']['taxed'];
            
            $linha = array('', 'variation','',$name,1,0,'visible','','','','','none','','','','',0,0,'','','','',0,'',$priceTaxed,$priceUntaxed, 
                            $categories,'','','','','',$sku,'','','','','',$position);
    
            array_push($linha, 'Quantity');
            array_push($linha, $quantities);
            array_push($linha, '');
            array_push($linha, 1);
            array_push($linha, '');
    
            foreach ($variationOption['selections'] as $filter => $options) {
                array_push($linha, $filter);
                array_push($linha, $options);
                array_push($linha, '');
                array_push($linha, 1);
                array_push($linha, '');
            }
            // Cria a linha CSV a partir do array de dados
            $csvLine = implode(',', $linha) . "\n";
            fwrite($fileHandle, $csvLine); // Escreve a linha no arquivo
            $position++;
        }
    
        fclose($fileHandle); // Fecha o arquivo
    }
    
    public function requestProduct($url)
    {
        // Realizar a requisição HTTP aqui usando wp_remote_get
        $response = wp_remote_get($url, array('sslverify' => false));
    
        // Verificar se ocorreu algum erro na requisição
        if (is_wp_error($response)) {
            throw new Exception('Erro ao realizar a requisição HTTP: ' . $response->get_error_message());
        }
    
        // Obter o corpo da resposta
        $json = wp_remote_retrieve_body($response);
    
        // Decodificar o JSON
        $array = json_decode($json, true);
    
        return $array;
    }
    
    public function generateCSV($data, $sku, $name, $categories, $quantities, $product, $options, $version)
    {
        $nameFile = str_replace(" ", "_", $name);
        
        $position = 0;
        // Defina as colunas para o CSV
        $colunas = array(
            'ID',
            'Type',
            'SKU',
            'Name',
            'Published',
            'Is featured?',
            'Visibility in catalog',
            'Short description',
            'Description',
            'Date sale price starts',
            'Date sale price ends',
            'Tax status',
            'Tax class',
            'In stock?',
            'Stock',
            'Low stock amount',
            'Backorders allowed?',
            'Sold individually?',
            'Weight (kg)',
            'Length (cm)',
            'Width (cm)',
            'Height (cm)',
            'Allow customer reviews?',
            'Purchase note',
            'Sale price',
            'Regular price',
            'Categories',
            'Tags',
            'Shipping class',
            'Images',
            'Download limit',
            'Download expiry days',
            'Parent',
            'Grouped products',
            'Upsells',
            'Cross-sells',
            'External URL',
            'Button text',
            'Position'
        );
        $contador = 1;
    
        if ($data['compatibleQuantities']) {
            array_push($colunas, 'Attribute ' . $contador . ' name');
            array_push($colunas, 'Attribute ' . $contador . ' value(s)');
            array_push($colunas, 'Attribute ' . $contador . ' visible');
            array_push($colunas, 'Attribute ' . $contador . ' global');
            array_push($colunas, 'Attribute ' . $contador . ' default');
    
            $contador = $contador + 1;
        }
    
        $url = $this->url_product . $product . "?pricingContext=eyJtZXJjaGFudElkIjoiVklTVEFQUklOVCIsIm1hcmtldCI6IkNBIiwiY3VzdG9tZXJHcm91cHMiOlsic2l0ZV90YWdnaW5nX25ld19jdXN0b21lciIsIm5ld191c2VyX2hlcm8iXX0&quantity=" . $quantities . "&productVersion=" . $version . "&requestor=UploadFlow&optimizelyEndUserId=_0f681020-8d54-4dc2-ad63-784cacd27c51";
        $optionsRequest = $this->requestProduct($url);
    
        if ($data['compatibleOptions']) {
            foreach ($data['compatibleOptions'] as $key => $option) {
                if ($option[0] != '' && $option[0] != 'None' && array_key_exists($key, $optionsRequest['options'][0]['properties'])) {
                    array_push($colunas, 'Attribute ' . $contador . ' name');
                    array_push($colunas, 'Attribute ' . $contador . ' value(s)');
                    array_push($colunas, 'Attribute ' . $contador . ' visible');
                    array_push($colunas, 'Attribute ' . $contador . ' global');
                    array_push($colunas, 'Attribute ' . $contador . ' default');
                    $contador = $contador + 1;
                }
            }
        }
    
        // Crie um arquivo temporário para gravar os dados do CSV
        $arquivo_temp = fopen('php://temp', 'w');
    
        // Escreva a linha de cabeçalho no CSV
        fputcsv($arquivo_temp, $colunas);
        // Adicione os valores à linha do CSV
        $linha = array('','variable',$sku,$name,1,0,'visible','','','','','none','','','','',0,0,'','','','',0,'',$data['pricing']['default'][$quantities]['totalListPrice']['taxed'],$data['pricing']['default'][$quantities]['totalListPrice']['untaxed'],$categories,'','','','','','','','','','','',$position,
                      );
        $contador = 1;
    
        if ($data['compatibleQuantities']) {
            array_push($linha, 'Quantity');
            array_push($linha, implode(', ', $data['compatibleQuantities']));
            array_push($linha, 1);
            array_push($linha, 1);
            array_push($linha, 100);
            $contador = $contador + 1;
        }
    
        if ($data['compatibleOptions']) {
            $contador = 1;
            foreach ($data['compatibleOptions'] as $key => $option) {
                if ($option[0] != '' && $option[0] != 'None' && array_key_exists($key, $optionsRequest['options'][0]['properties'])) {
                    array_push($linha, $key);
                    array_push($linha, implode(', ', $option));
                    array_push($linha, 1);
                    array_push($linha, 1);
                    array_push($linha, 100);
                    $contador = $contador + 1;
                }
            }
        }
    
        // Escreva a linha no CSV
        fputcsv($arquivo_temp, $linha);
    
        // Mova o ponteiro do arquivo para o início
        rewind($arquivo_temp);
    
        // Obtenha os dados CSV do arquivo temporário
        $dados_csv_str = stream_get_contents($arquivo_temp);
    
        // Feche o arquivo temporário
        fclose($arquivo_temp);
    
        // Defina o caminho do diretório onde o arquivo será salvo (substitua 'caminho_do_diretorio' pelo caminho correto)
        $caminho_diretorio = plugin_dir_path(__FILE__);
    
        // Defina o nome do arquivo CSV (substitua 'seu_nome_de_arquivo' pelo nome desejado)
        $nome_arquivo = $nameFile . '.csv';
    
        // Caminho completo do arquivo CSV (diretório + nome do arquivo)
        $caminho_arquivo = $caminho_diretorio . $nome_arquivo;
    
        // Salve os dados CSV em um arquivo no servidor
        file_put_contents($caminho_arquivo, $dados_csv_str);
    
        $caminho_arquivo = str_replace("/home/monarktek/htdocs/", "", $caminho_arquivo);
        // Retorne o caminho completo do arquivo, para que possamos usar esse caminho no link de download
        return $caminho_arquivo;
    }
    
}

new InsertProductsPlugin();
