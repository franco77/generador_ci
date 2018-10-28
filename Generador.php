<?php
/**
 * 
 * Generador de código de CodeIgniter sólo por diversión
 * @author parzibyte
 * @web parzibyte.me/blog
 * 
 */
class Generador
{
    private $bd;
    private $nombreDeLaBaseDeDatos;
    private $directorioDeSalida;
    private $fechaYHora;

    private $encabezadoModelo;
    private $encabezadoControlador;
    private $encabezadoVista;

    private $tablasAIgnorar;

    const AUTOR = "@parzibyte";
    const NOMBRE_COL_LLAVE_PRIMARIA = "id";
    const NUEVA_LINEA = "\n";
    const SUFIJO_MODELO = "Model";

    public function __construct($host, $usuario, $pass, $nombreDeLaBaseDeDatos, $directorioDeSalida = "generado")
    {
        $this->fechaYHora = date("Y-m-d H:i:s");
        $this->directorioDeSalida = __DIR__ . DIRECTORY_SEPARATOR . $directorioDeSalida . DIRECTORY_SEPARATOR;
        $this->prepararDirectorioDeSalida();
        $this->prepararEncabezados();
        $this->nombreDeLaBaseDeDatos = $nombreDeLaBaseDeDatos;
        $this->bd = new PDO("mysql:host=$host;dbname=$nombreDeLaBaseDeDatos", $usuario, $pass);
        $this->tablasAIgnorar = [];
    }

    public function setTablasAIgnorar($tablas)
    {
        $this->tablasAIgnorar = $tablas;
    }

    public function generar()
    {
        echo "
    *********************
        COMENZANDO
    **********************
    ";
        $tablas = $this->obtenerTablasDeLaBaseDeDatos();
        foreach ($tablas as $tabla) {

            if (!in_array(strtolower($tabla->nombre), $this->tablasAIgnorar)) {
                echo "\nCreando modelo para " . $tabla->nombre . " ...";
                $nombreDelModelo = $this->crearModelo($tabla->nombre);
                echo "OK";
                echo "\nCreando controlador para " . $tabla->nombre . "...";
                $nombreDelControlador = $this->crearControlador($tabla->nombre, $nombreDelModelo);
                echo "OK";

                echo "\nCreando vista para mostrar datos...";
                $this->crearVistaParaMostrarDatos($nombreDelControlador);
                echo "OK";
                echo "\nCreando vista para insertar datos...";
                $this->crearVistaDeFormularioParaInsertar($nombreDelControlador);
                echo "OK";
                echo "\nCreando vista para editar datos...";
                $this->crearVistaDeFormularioParaEditar($nombreDelControlador);
                echo "OK";
            } else {
                echo "\nIgnorando tabla " . $tabla->nombre;
            }
        }
        echo "
==================
Proceso terminado
==================";
    }

    private function crearModelo($nombreDeLaTabla)
    {
        $nombreDelModelo = $nombreDeLaTabla . self::SUFIJO_MODELO;

        # Declarar propiedades y argumentos

        $argumentos = "";
        $argumentosUpdate = "";
        $arregloUpdate = "[
";
        $arregloInsert = "[
";

        $columnas = $this->obtenerColumnasDeTabla($nombreDeLaTabla);
        $numeroDeColumnas = count($columnas);
        $miembros = "";
        foreach ($columnas as $indice => $columna) {
            $miembros .= sprintf('
    private $%s;', $columna);
            if ($columna !== self::NOMBRE_COL_LLAVE_PRIMARIA) {
                $argumentos .= '$' . $columna;
                $arregloInsert .= sprintf('                     "%s" => $%s,
', $columna, $columna);
                $arregloUpdate .= sprintf('             "%s" => $%s,
', $columna, $columna);
                if ($indice < $numeroDeColumnas - 1) {
                    $argumentos .= ", ";
                }
            }
            $argumentosUpdate .= '$' . $columna;
            if ($indice < $numeroDeColumnas - 1) {
                $argumentosUpdate .= ", ";
            }
        }
        $arregloInsert .= "                       ]";
        $arregloUpdate .= "          ]";

        $miembros .= "

";

        # Constructor, cargar BD
        $codigo = sprintf('
<?php
%s
class %s extends CI_Model{
    %s
    public function __construct(){
        parent::__construct();
        $this->load->database();
    }


', $this->encabezadoModelo, $nombreDelModelo, $miembros);

        # Insert [Crud]
        $codigo .= sprintf('

    public function insertar(%s){
        return $this->db->insert("%s", %s);
    }
    ', $argumentos, $nombreDeLaTabla, $arregloInsert);

        $codigo .= sprintf('
    public function obtener($pagina = 1){
        if(intval($pagina) === 1){
            $pagina = 0;
        }else{
            $pagina--;
        }
        return $this->db->get("%s", DATOS_MOSTRAR_POR_PAGINA, $pagina * DATOS_MOSTRAR_POR_PAGINA)->result();
    }
    ', $nombreDeLaTabla);

        # total de filas
        $codigo .= sprintf('
    public function totalDeFilas(){
        return $this->db->count_all("%s");
    }', $nombreDeLaTabla);

        # Obtener por id
        $codigo .= sprintf('

    public function porId($id){
        return $this->db->get_where("%s", ["id" => $id])->row();
    }
    ', $nombreDeLaTabla);

        # Update [crUd]
        $codigo .= sprintf('
    public function guardarCambios(%s){
        $datos = %s;
        return $this->db->update("%s", $datos, ["%s" => $%s]);
    }
    ', $argumentosUpdate, $arregloUpdate, $nombreDeLaTabla, self::NOMBRE_COL_LLAVE_PRIMARIA, self::NOMBRE_COL_LLAVE_PRIMARIA);

        #Delete [cruD]
        $codigo .= sprintf('
    public function eliminar($%s){
        return $this->db->delete("%s", ["%s" => $%s]);
    }
    ', self::NOMBRE_COL_LLAVE_PRIMARIA, $nombreDeLaTabla, self::NOMBRE_COL_LLAVE_PRIMARIA, self::NOMBRE_COL_LLAVE_PRIMARIA);

        $codigo .= '
}
?>
';

        $nombreDeArchivo = $this->directorioDeSalida . "models/" . $nombreDelModelo . ".php";
        $bytesEscritos = file_put_contents($nombreDeArchivo, $codigo);
        if ($bytesEscritos !== false) {
            return $nombreDelModelo;
        } else {
            throw new Exception("¡No se pudo escribir el modelo $nombreDelModelo!");
        }
    }

    private function crearControlador($nombreDeLaTabla, $nombreDelModelo)
    {
        $codigo = sprintf("<?php
%s
", $this->encabezadoControlador);
        $codigo .= "class $nombreDeLaTabla extends CI_Controller{";
        # Primero el constructor

        $codigo .= sprintf('
        public function __construct(){
            parent::__construct();
            $this->load->model("%s");
        }', $nombreDelModelo);

        # El método que renderiza
        $codigo .= sprintf('
        public function index(){
            $this->listar(1);
        }', $nombreDeLaTabla, $nombreDeLaTabla, $nombreDelModelo, $nombreDelModelo);

        # Obtener como tabla
        $codigo .= sprintf('
        public function listar($pagina = 1){
            $totalDeFilas = $this->%s->totalDeFilas();
            $paginas = ceil( $totalDeFilas / DATOS_MOSTRAR_POR_PAGINA);
            $this->load->view("encabezado");
            $this->load->view("%s",
                [
                    "titulo" => "%s",
                    "datos" => $this->%s->obtener($pagina),
                    "paginaActual" => $pagina,
                    "paginas" => $paginas,
                ]
            );
            $this->load->view("pie");
        }', $nombreDelModelo, $nombreDeLaTabla, ucfirst($nombreDeLaTabla), $nombreDelModelo);

        # Obtener como JSON
        $codigo .= sprintf('
        public function json($pagina = 1){
            echo json_encode($this->%s->obtener($pagina), JSON_NUMERIC_CHECK);
        }', $nombreDelModelo);

        $argumentos = "";
        $argumentosUpdate = "";
        $arregloUpdate = "";
        $arregloInsert = "";

        $columnas = $this->obtenerColumnasDeTabla($nombreDeLaTabla);
        $numeroDeColumnas = count($columnas);
        foreach ($columnas as $indice => $columna) {
            $arregloUpdate .= sprintf('$this->input->post("%s")', $columna);
            if ($columna !== self::NOMBRE_COL_LLAVE_PRIMARIA) {
                $argumentos .= '$' . $columna;
                $arregloInsert .= sprintf('$this->input->post("%s")', $columna);
                if ($indice < $numeroDeColumnas - 1) {
                    $arregloInsert .= ",
            ";
                    $argumentos .= ", ";
                }
            }
            $argumentosUpdate .= '$' . $columna;
            if ($indice < $numeroDeColumnas - 1) {
                $argumentosUpdate .= ", ";
                $arregloUpdate .= ",
            ";
            }
        }

        #Eliminar
        $codigo .= sprintf('
        public function eliminar($id){
            $resultado = $this->%s->eliminar($id);
            if($resultado){
                $mensaje = "%s eliminado correctamente";
                $clase = "is-success";
            }else{
                $mensaje = "Error al eliminar %s";
                $clase = "is-danger";
            }
            $this->session->set_flashdata(array(
                "mensaje" => $mensaje,
                "clase" => $clase,
            ));
            redirect("%s/");
        }', $nombreDelModelo, $nombreDeLaTabla, $nombreDeLaTabla, $nombreDeLaTabla);

        #Renderizar formulario para editar
        $codigo .= sprintf('

        public function editar($id){
            $datoParaEditar = $this->%s->porId($id);
            $this->load->view("encabezado");
            $this->load->view("%s_editar", ["dato" => $datoParaEditar]);
            $this->load->view("pie");
        }', $nombreDelModelo, $nombreDeLaTabla);

        #Renderizar formulario para insertar
        $codigo .= sprintf('

        public function agregar(){
            $this->load->view("encabezado");
            $this->load->view("%s_agregar");
            $this->load->view("pie");
        }', $nombreDeLaTabla);

        #Insertar

        $codigo .= sprintf('
        public function insertar(){
            $resultado = $this->%s->insertar(%s);
            if($resultado){
                $mensaje = "%s insertado correctamente";
                $clase = "is-success";
            }else{
                $mensaje = "Error al insertar %s";
                $clase = "is-danger";
            }
            $this->session->set_flashdata(array(
                "mensaje" => $mensaje,
                "clase" => $clase,
            ));
            redirect("%s/");
        }', $nombreDelModelo, $arregloInsert, $nombreDeLaTabla, $nombreDeLaTabla, $nombreDeLaTabla);

        #Actualizar

        $codigo .= sprintf('
        public function actualizar(){
            $resultado = $this->%s->guardarCambios(%s);
            if($resultado){
                $mensaje = "%s actualizado correctamente";
                $clase = "is-success";
            }else{
                $mensaje = "Error al actualizar %s";
                $clase = "is-danger";
            }
            $this->session->set_flashdata(array(
                "mensaje" => $mensaje,
                "clase" => $clase,
            ));
            redirect("%s/");
        }', $nombreDelModelo, $arregloUpdate, $nombreDeLaTabla, $nombreDeLaTabla, $nombreDeLaTabla);

        # Fin del código
        $codigo .= '
    }
    ?>';

        $nombreDeArchivo = $this->directorioDeSalida . "/controllers/" . $nombreDeLaTabla . ".php";
        $bytesEscritos = file_put_contents($nombreDeArchivo, $codigo);
        if ($bytesEscritos !== false) {
            return $nombreDeLaTabla;
        } else {
            throw new Exception("¡No se pudo escribir el controlador $nombreDeLaTabla!");
        }
    }

    private function crearVistaParaMostrarDatos($nombreDeLaTabla)
    {
        $codigo = "";

        $codigo .= '
            <h1 class="is-size-1"><?php echo $titulo; ?></h1>';
        $codigo .= sprintf('
            <div class="columns">
                <div class="column">
                    <a href="<?php echo base_url() ?>index.php/%s/agregar" class="button is-warning">Agregar</a>
                </div>
            </div>',
            $nombreDeLaTabla);

        $columnas = $this->obtenerColumnasDeTabla($nombreDeLaTabla);
        $numeroDeColumnas = count($columnas);

        $codigo .= '

            <div class="columns">
                <div class="column">
                    <table class="table is-bordered is-hoverable">
                        <thead>
                            <tr>';

        $encabezado = "";

        foreach ($columnas as $indice => $columna) {
            $encabezado .= '
                                <th>' . $columna . '</th>';
        }
        // Agregar edit y delete
        $encabezado .= '
                                <th>Editar</th>
                                <th>Eliminar</th>';

        #Concatenar encabezado
        $codigo .= $encabezado;

        $codigo .= '        </tr>
                        <thead>
                        <tbody>
                        <?php foreach($datos as $dato){ ?>
                            <tr>';
        foreach ($columnas as $indice => $columna) {
            $codigo .= '
                                <td>';
            $codigo .= sprintf('
                                    <?php echo $dato->%s; ?>
                                </td>', $columna);
        }

        #Agregar botones

        $codigo .= sprintf('
                                <td>
                                    <a href="<?php echo base_url() . "index.php/%s/editar/" . $dato->id ?>" class="button">
                                        <span class="icon">
                                        <i class="fa fa-edit has-text-info"></i>
                                        </span>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo base_url() . "index.php/%s/eliminar/" . $dato->id ?>" class="button">
                                        <span class="icon">
                                        <i class="fa fa-trash has-text-danger"></i>
                                        </span>
                                    </a>
                                </td>',
            $nombreDeLaTabla, $nombreDeLaTabla);

        $codigo .= sprintf('
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <nav class="pagination is-rounded" role="navigation" aria-label="pagination">
                        <?php if($paginaActual > 1){ ?>
                            <a href="<?php echo base_url() ?>index.php/%s/listar/<?php echo $paginaActual - 1 ?>" class="pagination-previous">Anterior</a>
                        <?php } ?>
                        <?php if($paginaActual < $paginas){ ?>
                            <a href="<?php echo base_url() ?>index.php/%s/listar/<?php echo $paginaActual + 1 ?>" class="pagination-next">Siguiente</a>
                        <?php } ?>
                        <ul class="pagination-list">
                            <?php for ($numeroDePagina = 1; $numeroDePagina <= $paginas; $numeroDePagina++) { ?>
                                <li>
                                    <a href="<?php echo base_url() ?>index.php/%s/listar/<?php echo $numeroDePagina ?>"
                                       class="pagination-link <?php echo intval($paginaActual) === intval($numeroDePagina) ? "is-current" : "" ?>"
                                       aria-label="Ir a la página <?php echo $numeroDePagina ?>">
                                        <?php echo $numeroDePagina ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </nav>
                </div>
            </div>', $nombreDeLaTabla, $nombreDeLaTabla, $nombreDeLaTabla);
        $codigo = sprintf('
    %s
    <div class="columns">
        <div class="column">
            <?php if (!empty($this->session->flashdata())): ?>
                <div class="notification <?php echo $this->session->flashdata("clase") ?>">
                    <?php echo $this->session->flashdata("mensaje") ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="columns">
        <div class="column">
        %s
        </div>
    </div>
',
            sprintf('<?php
%s
?>', $this->encabezadoVista), $codigo);
        $nombreDeArchivo = $this->directorioDeSalida . "/views/" . $nombreDeLaTabla . ".php";
        $bytesEscritos = file_put_contents($nombreDeArchivo, $codigo);
        if ($bytesEscritos !== false) {
            return $nombreDeLaTabla;
        } else {
            throw new Exception("¡No se pudo escribir la vista $nombreDeLaTabla!");
        }
    }

    private function crearVistaDeFormularioParaInsertar($nombreDeLaTabla)
    {
        $codigo = '';

        $columnas = $this->obtenerColumnasDeTablaParaFormulario($nombreDeLaTabla);
        $numeroDeColumnas = count($columnas);

        $campos = "";
        foreach ($columnas as $indice => $columnaObj) {
            $columna = $columnaObj->columna;
            $tipoDeDato = $columnaObj->tipoDeDato;
            if ($columna === self::NOMBRE_COL_LLAVE_PRIMARIA) {
                continue;
            }

            $tipoDeInput = "text";
            switch ($tipoDeDato) {
                case "bigint":
                case "decimal":
                    $tipoDeInput = "number";
                    break;
                case "datetime":
                    $tipoDeInput = "datetime-local";
                    break;
                case "date":
                    $tipoDeInput = "date";
                    break;
            }

            if ($tipoDeInput !== "checkbox") {

                $campos .= sprintf('
                <div class="field">
                    <label class="label" for="%s">Ingrese %s</label>
                    <div class="control">
                        <input autocomplete="off" class="input" required id="%s" type="%s" name="%s" placeholder="Escribe aquí %s">
                    </div>
                </div>
                ', $columna, $columna, $columna, $tipoDeInput, $columna, $columna);
            } else {
                $campos .= sprintf('
                <div class="field">
                    <label for="%s" class="checkbox">
                        <input id="%s" name="%s" type="checkbox">
                            ¿%s?
                    </label>
                </div>
                ', $columna, $columna, $columna, $columna);
            }
        }

        $codigo .= sprintf('
            <h1 class="is-size-1">Agregar %s</h1>
            <form method="post" action="<?php echo base_url() ?>index.php/%s/insertar">
                %s
                <button class="button is-success" type="submit">Guardar</button>
                <a href="<?php echo base_url() ?>index.php/%s" class="button is-primary">Volver</a>
            </form>
        ',
            $nombreDeLaTabla,
            $nombreDeLaTabla,
            $campos,
            $nombreDeLaTabla);

        $codigo = sprintf('
    %s
    <div class="columns">
        <div class="column">
            <?php if (!empty($this->session->flashdata())): ?>
                <div class="notification <?php echo $this->session->flashdata("clase") ?>">
                    <?php echo $this->session->flashdata("mensaje") ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="columns">
        <div class="column">
            %s
        </div>
    </div>
                ', sprintf('<?php
%s
?>', $this->encabezadoVista), $codigo);

        $nombreDeArchivo = $this->directorioDeSalida . "/views/" . $nombreDeLaTabla . "_agregar.php";
        $bytesEscritos = file_put_contents($nombreDeArchivo, $codigo);
        if ($bytesEscritos !== false) {
            return $nombreDeLaTabla;
        } else {
            throw new Exception("¡No se pudo escribir la vista del formulario para insertar $nombreDeLaTabla!");
        }
    }

    private function crearVistaDeFormularioParaEditar($nombreDeLaTabla)
    {
        $codigo = "";

        $columnas = $this->obtenerColumnasDeTablaParaFormulario($nombreDeLaTabla);
        $numeroDeColumnas = count($columnas);

        $campos = "";
        #Crear el tipo hidden
        $campos .= sprintf('
                <input type="hidden" name="id" value="%s">', '<?php echo $dato->id; ?>');
        foreach ($columnas as $indice => $columnaObj) {
            $columna = $columnaObj->columna;
            $tipoDeDato = $columnaObj->tipoDeDato;
            if ($columna === self::NOMBRE_COL_LLAVE_PRIMARIA) {
                continue;
            }

            $tipoDeInput = "text";
            switch ($tipoDeDato) {
                case "bigint":
                case "decimal":
                    $tipoDeInput = "number";
                    break;
                case "datetime":
                    $tipoDeInput = "datetime-local";
                    break;
                case "date":
                    $tipoDeInput = "date";
                    break;
            }
            if ($tipoDeInput !== "checkbox") {
                $campos .= sprintf('
                <div class="field">
                    <label class="label" for="%s">Ingrese %s</label>
                    <div class="control">
                        <input autocomplete="off" class="input" required id="%s" type="%s" name="%s" placeholder="Escribe aquí el %s" value="%s">
                    </div>
                </div>
            ', $columna, $columna, $columna, $tipoDeInput, $columna, $columna, sprintf('<?php echo $dato->%s; ?>', $columna));
            } else {
                $campos .= sprintf('
                <div class="field">
                    <label for="%s" class="checkbox">
                        <input id="%s" name="%s" type="checkbox" <?php echo $dato->%s ? "checked" : "" ?> >
                            ¿%s?
                    </label>
                </div>
                ', $columna, $columna, $columna, $columna, $columna);
            }

        }

        $codigo .= sprintf('
            <h1 class="is-size-1">Editar %s</h1>
            <form method="post" action="<?php echo base_url() ?>index.php/%s/actualizar">
                %s
                <button class="button is-success" type="submit">Guardar cambios</button>
                <a href="<?php echo base_url() ?>index.php/%s" class="button is-primary">Volver</a>
            </form>
        ',
            $nombreDeLaTabla,
            $nombreDeLaTabla,
            $campos,
            $nombreDeLaTabla);

        $codigo = sprintf('
    %s
    <div class="columns">
        <div class="column">
            <?php if (!empty($this->session->flashdata())): ?>
                <div class="notification <?php echo $this->session->flashdata("clase") ?>">
                    <?php echo $this->session->flashdata("mensaje") ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="columns">
        <div class="column">
            %s
        </div>
    </div>
    ',
            sprintf('<?php
%s
?>', $this->encabezadoVista), $codigo);

        $nombreDeArchivo = $this->directorioDeSalida . "/views/" . $nombreDeLaTabla . "_editar.php";
        $bytesEscritos = file_put_contents($nombreDeArchivo, $codigo);
        if ($bytesEscritos !== false) {
            return $nombreDeLaTabla;
        } else {
            throw new Exception("¡No se pudo escribir la vista del formulario para editar $nombreDeLaTabla!");
        }
    }

    private function prepararEncabezados()
    {
        $this->encabezadoModelo = sprintf('
        /*
            Modelo de CodeIgniter generado por un script programado por %s
            Fecha y hora de generación: %s
        */

        ', self::AUTOR, $this->fechaYHora);

        $this->encabezadoControlador = sprintf('
        /*
            Controlador de CodeIgniter generado por un script programado por %s
            Fecha y hora de generación: %s
        */

        ', self::AUTOR, $this->fechaYHora);

        $this->encabezadoVista = sprintf('
        /*
            Vista de CodeIgniter generada por un script programado por %s
            Fecha y hora de generación: %s
        */

        ', self::AUTOR, $this->fechaYHora);
    }

    private function prepararDirectorioDeSalida()
    {
        if (is_dir($this->directorioDeSalida)) {
            $this->eliminarDirectorioYSuContenido($this->directorioDeSalida);
        }

        mkdir($this->directorioDeSalida);
        mkdir($this->directorioDeSalida . "models");
        mkdir($this->directorioDeSalida . "controllers");
        mkdir($this->directorioDeSalida . "views");
    }

    private function obtenerTablasDeLaBaseDeDatos()
    {
        return $this
            ->bd
            ->query("SELECT table_name AS nombre FROM information_schema.tables WHERE table_schema = '" . $this->nombreDeLaBaseDeDatos . "';")
            ->fetchAll(PDO::FETCH_OBJ);
    }

    private function obtenerColumnasDeTabla($tabla)
    {
        return $this
            ->bd
            ->query("SELECT COLUMN_NAME AS columna
                FROM information_schema.columns
                WHERE table_schema = '" . $this->nombreDeLaBaseDeDatos . "'
                AND TABLE_NAME = '" . $tabla . "'")
            ->fetchALL(PDO::FETCH_COLUMN);
    }

    public function obtenerColumnasDeTablaParaFormulario($tabla)
    {
        return $this
            ->bd
            ->query("SELECT COLUMN_NAME AS columna, DATA_TYPE AS tipoDeDato
                FROM information_schema.columns WHERE table_schema = '" . $this->nombreDeLaBaseDeDatos . "'
                AND TABLE_NAME = '$tabla'")
            ->fetchALL(PDO::FETCH_OBJ);
    }

    private function eliminarDirectorioYSuContenido($directorio)
    {
        if (!file_exists($directorio)) {
            return true;
        }

        if (!is_dir($directorio)) {
            return unlink($directorio);
        }

        foreach (scandir($directorio) as $elementoActual) {
            if ($elementoActual == '.' || $elementoActual == '..') {
                continue;
            }

            if (!$this->eliminarDirectorioYSuContenido($directorio . DIRECTORY_SEPARATOR . $elementoActual)) {
                return false;
            }

        }
        return rmdir($directorio);
    }
}
