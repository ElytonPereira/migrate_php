<?php
/*
  Descrição do Desafio:
    Você precisa realizar uma migração dos dados fictícios que estão na pasta <dados_sistema_legado> para a base da clínica fictícia MedicalChallenge.
    Para isso, você precisa:
      1. Instalar o MariaDB na sua máquina. Dica: Você pode utilizar Docker para isso;
      2. Restaurar o banco da clínica fictícia Medical Challenge: arquivo <medical_challenge_schema>;
      3. Migrar os dados do sistema legado fictício que estão na pasta <dados_sistema_legado>:
        a) Dica: você pode criar uma função para importar os arquivos do formato CSV para uma tabela em um banco temporário no seu MariaDB.
      4. Gerar um dump dos dados já migrados para o banco da clínica fictícia Medical Challenge.
*/

// Importação de Bibliotecas:
include "./lib.php";

function convertDate($date) {
  $dateArray = explode('/', $date);
  if (count($dateArray) === 3) {
      return $dateArray[2] . '-' . $dateArray[1] . '-' . $dateArray[0];
  }
  return $date; 
}

function mapSexo($sexo_pac){
  $map = [
    "M" => "Masculino",
    "F" => "Feminino"
  ];
  return $map[$sexo_pac] ?? "Outro";
}

// Conexão com o banco da clínica fictícia:
$connMedical = mysqli_connect("localhost", "root", "36217900", "medicalchallenge")
  or die("Não foi possível conectar os servidor MySQL: medicalchallenge\n");

  mysqli_set_charset($connMedical, 'utf8mb4');


// Informações de Inicio da Migração:
echo "Início da Migração: " . dateNow() . ".\n\n";


$arquivo = fopen("20210512_pacientes.csv", "r");
if ($arquivo === false) {
  die("Não foi possível abrir o arquivo CSV.\n");
}

// Ler o cabeçalho do CSV
$header = fgetcsv($arquivo, 1000, ";");
if ($header === false) {
  die("Erro ao ler o cabeçalho do arquivo CSV.\n");
}

//Convenio
while ($row = fgetcsv($arquivo, 1000, ";")) {

  $sqlByUltID = "SELECT MAX(id) AS last_id FROM convenios";
  $result = mysqli_query($connMedical, $sqlByUltID);

  if ($result) {
      $rowSql = mysqli_fetch_assoc($result);
      $lastId = $rowSql['last_id'] ?? 0;
      $newId = $lastId + 1;
  } else {
      die("Erro ao buscar o último ID: " . mysqli_error($connMedical) . "\n");
  }

  $data = array_combine($header, $row);
 
  $nome_convenio = empty($data["convenio"]) ? "" : $data["convenio"]; 

   $sqlByNome = "SELECT nome FROM convenios WHERE nome = '$nome_convenio'";
   $resultSqlByNome = mysqli_query($connMedical, $sqlByNome);

  if (mysqli_num_rows($resultSqlByNome) >0) {
    echo "Convênio '$nome_convenio' encontrado no banco de dados.\n";    
    continue;
  }
    
  $sqlInsertConvenio = "INSERT INTO convenios (id, nome) VALUES (?, ?)";

  $stmtConvenio = mysqli_prepare($connMedical, $sqlInsertConvenio);

  if ($stmtConvenio === false) {
    die("Erro ao preparar a consulta SQL: " . mysqli_error($connMedical) . "\n");
}

mysqli_stmt_bind_param($stmtConvenio, 'is', $newId, $nome_convenio);

if (mysqli_stmt_execute($stmtConvenio)) {
  echo "Registro inserido na tabela convenios com sucesso!!.\n";    
} else{
  echo "Erro ao inserir dados na tabela convenio: " . mysqli_stmt_error($stmtConvenio) . "\n";
}

mysqli_stmt_close($stmtConvenio);

}

/////////////////////////////////////////////////////////////////////////////////////

$arquivo1 = fopen("20210512_pacientes.csv", "r");
if ($arquivo1 === false) {
  die("Não foi possível abrir o arquivo CSV.\n");
}

$header = fgetcsv($arquivo1, 1000, ";");

//Pacientes
while ($row = fgetcsv($arquivo1, 1000, ";")) {

  $sqlByUltID = "SELECT MAX(id) AS last_id FROM pacientes";
  $result = mysqli_query($connMedical, $sqlByUltID);

  if ($result) {
      $rowSql = mysqli_fetch_assoc($result);
      $lastId = $rowSql['last_id'] ?? 0;
      $newId = $lastId + 1;
  } else {
      die("Erro ao buscar o último ID: " . mysqli_error($connMedical) . "\n");
  }

  $data = array_combine($header, $row);
  
  $cod_paciente = intval($data['cod_paciente'] ?? "");
  $nome_paciente = $data['nome_paciente'] ?? "";
  $nasc_paciente = convertDate($data['nasc_paciente'] ?? "");
  $cpf_paciente = $data['cpf_paciente'] ?? "";
  $rg_paciente = $data['rg_paciente'] ?? "";
  $sexo_pac = mapSexo($data['sexo_pac'] ?? "");
  $nome_convenio = $data['convenio'];
  
  if($cod_paciente === 0){
    echo "Paciente com código 0, pulando...\n";
    continue;
  }

  echo "Nome do convenio $nome_convenio: ";
  $sqlByNome = "SELECT id FROM convenios WHERE nome = '$nome_convenio'";
  $resultSqlByIdConv = mysqli_query($connMedical, $sqlByNome);
  $result2 = mysqli_fetch_assoc($resultSqlByIdConv);
  $id_convenioSql = $result2['id'] ?? 0;

  print_r($id_convenioSql);

  // Inserir os dados na tabela do banco de dados
  $sqlInsertPaciente = "INSERT INTO pacientes (id, nome, nascimento, cpf, rg, sexo, id_convenio, cod_referencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ";
  $stmtPaciente = mysqli_prepare($connMedical, $sqlInsertPaciente);

  if ($stmtPaciente === false) {
      die("Erro ao preparar a consulta SQL: " . mysqli_error($connMedical) . "\n");
  }

  mysqli_stmt_bind_param($stmtPaciente, 'isssssii', $newId, $nome_paciente, $nasc_paciente, $cpf_paciente, $rg_paciente, $sexo_pac, $id_convenioSql, $cod_paciente);

  if (mysqli_stmt_execute($stmtPaciente)) {
    echo "Registro inserido na tabela pacientes com sucesso!!.\n";

  }else{
    echo "Erro ao inserir dados: " . mysqli_stmt_error($stmtPaciente) . "\n";

  }

  mysqli_stmt_close($stmtPaciente);

}

////////////////////////////////////////////////////////////////

$arquivo2 = fopen("20210512_agendamentos.csv", "r");
if ($arquivo2 === false) {
  die("Não foi possível abrir o arquivo CSV.\n");
}

$header = fgetcsv($arquivo2, 1000, ";");

//Profissionais
while ($row = fgetcsv($arquivo2, 1000, ";")) {
  
  $sqlByUltID = "SELECT MAX(id) AS last_id FROM profissionais";
  $result = mysqli_query($connMedical, $sqlByUltID);

  if ($result) {
      $rowSql = mysqli_fetch_assoc($result);
      $lastId = $rowSql['last_id'] ?? 0;
      $newId = $lastId + 1;
  } else {
      die("Erro ao buscar o último ID: " . mysqli_error($connMedical) . "\n");
  }

  $data = array_combine($header, $row);

  $nome_profissional = $data["medico" ?? ""];

  $sqlByNome = "SELECT nome FROM profissionais WHERE nome = '$nome_profissional'";
  $resultSqlByNome = mysqli_query($connMedical, $sqlByNome);

 if (mysqli_num_rows($resultSqlByNome) >0) {
   echo "Profissional '$nome_profissional' encontrado no banco de dados.\n";    
   continue;
 }

 $sqlInsertProfissional = "INSERT INTO profissionais (id, nome) VALUES (?, ?)";

 $stmtProfissional = mysqli_prepare($connMedical, $sqlInsertProfissional);

 if ($stmtProfissional === false) {
   die("Erro ao preparar a consulta SQL: " . mysqli_error($connMedical) . "\n");
}

mysqli_stmt_bind_param($stmtProfissional, 'is', $newId, $nome_profissional);

if (mysqli_stmt_execute($stmtProfissional)) {
 echo "Registro inserido na tabela Profissional com sucesso!!.\n";    
} else{
 echo "Erro ao inserir dados na tabela Profissional: " . mysqli_stmt_error($stmtProfissional) . "\n";
}

mysqli_stmt_close($stmtProfissional);

}

////////////////////////////////////////////////////////////////////////

$arquivo3 = fopen("20210512_agendamentos.csv", "r");
if ($arquivo3 === false) {
  die("Não foi possível abrir o arquivo CSV.\n");
}

$header = fgetcsv($arquivo3, 1000, ";");

//Procedimentos
while ($row = fgetcsv($arquivo3, 1000, ";")) {

  $sqlByUltID = "SELECT MAX(id) AS last_id FROM procedimentos";
  $result = mysqli_query($connMedical, $sqlByUltID);

  if ($result) {
      $rowSql = mysqli_fetch_assoc($result);
      $lastId = $rowSql['last_id'] ?? 0;
      $newId = $lastId + 1;
  } else {
      die("Erro ao buscar o último ID: " . mysqli_error($connMedical) . "\n");
  }

  $data = array_combine($header, $row);

  $nome_procedimento = $data["procedimento" ?? ""];

  $sqlByNome = "SELECT nome FROM procedimentos WHERE nome = '$nome_procedimento'";
  $resultSqlByNome = mysqli_query($connMedical, $sqlByNome);

 if (mysqli_num_rows($resultSqlByNome) >0) {
   echo "nome_procedimento '$nome_procedimento' encontrado no banco de dados.\n";    
   continue;
 }

 $sqlInsertProcedimento = "INSERT INTO procedimentos (id, nome) VALUES (?, ?)";

 $stmtProcedimento = mysqli_prepare($connMedical, $sqlInsertProcedimento);

 if ($stmtProcedimento === false) {
   die("Erro ao preparar a consulta SQL: " . mysqli_error($connMedical) . "\n");
}

mysqli_stmt_bind_param($stmtProcedimento, 'is', $newId, $nome_Procedimento);

if (mysqli_stmt_execute($stmtProcedimento)) {
 echo "Registro inserido na tabela Profissional com sucesso!!.\n";    
} else{
 echo "Erro ao inserir dados na tabela Profissional: " . mysqli_stmt_error($stmtProcedimento) . "\n";
}

mysqli_stmt_close($stmtProcedimento);

}

////////////////////////////////////////////////////////////////////////

$arquivo4 = fopen("20210512_agendamentos.csv", "r");
if ($arquivo4 === false) {
  die("Não foi possível abrir o arquivo CSV.\n");
}

$header = fgetcsv($arquivo4, 1000, ";");

//Agendamentos
while ($row = fgetcsv($arquivo4, 1000, ";")) {

  $sqlByUltID = "SELECT MAX(id) AS last_id FROM agendamentos";
  $result = mysqli_query($connMedical, $sqlByUltID);

  if ($result) {
      $rowSql = mysqli_fetch_assoc($result);
      $lastId = $rowSql['last_id'] ?? 0;
      $newId = $lastId + 1;
  } else {
      die("Erro ao buscar o último ID: " . mysqli_error($connMedical) . "\n");
  }

  $data = array_combine($header, $row);
 
  $cod_agendamento = $data['cod_agendamento'];
  $observacoes = $data['descricao'];
  $dia = convertDate($data['dia']);
  $hora_incio = $data['hora_inicio'];
  $hora_fim = $data['hora_fim'] ?? "";
  $cod_paciente_legado = $data['cod_paciente'];
  $nome_profissional = $data['medico'];
  $nome_convenio = $data['convenio'];
  $nome_procedimento = $data['procedimento'];

  $dh_inicio = $dia . ' ' . $hora_incio;
  $dh_fim = $dia . ' ' . $hora_fim;
  
  if($cod_agendamento === 0){
    echo "Agendamento com código 0, pulando...\n";
    continue;
  }

  $sqlByIdConvenio = "SELECT id FROM convenios WHERE nome = '$nome_convenio'";
  $resultSqlByIdConv = mysqli_query($connMedical, $sqlByIdConvenio);
  $resultConvenio = mysqli_fetch_assoc($resultSqlByIdConv);
  $id_convenioSql = $resultConvenio['id'] ?? 0;
  
  $sqlByIdPaciente = "SELECT id FROM pacientes WHERE cod_referencia = '$cod_paciente_legado'";
  $resultSqlByIdPac = mysqli_query($connMedical, $sqlByIdPaciente);
  $resultPaciente = mysqli_fetch_assoc($resultSqlByIdPac);
  $id_PacienteSql = $resultPaciente['id'] ?? 0;
  
  $sqlByIdProfissional = "SELECT id FROM profissionais WHERE nome = '$nome_profissional'";
  $resultSqlByIdPro = mysqli_query($connMedical, $sqlByIdProfissional);
  $resultProfissional = mysqli_fetch_assoc($resultSqlByIdPro);
  $id_ProfissionalSql = $resultProfissional['id'] ?? 0;
  
  $sqlByIdProcedimento = "SELECT id FROM procedimentos WHERE nome = '$nome_procedimento'";
  $resultSqlByIdProc = mysqli_query($connMedical, $sqlByIdProcedimento);
  $resultProcedimento = mysqli_fetch_assoc($resultSqlByIdProc);
  $id_ProcedimentoSql = $resultProcedimento['id'] ?? 0;

  // Inserir os dados na tabela do banco de dados
  $sqlInsertProcedimento = "INSERT INTO agendamentos (id, id_paciente, id_profissional, dh_inicio, dh_fim, id_convenio, id_procedimento, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ";
  $stmtProcedimento = mysqli_prepare($connMedical, $sqlInsertProcedimento);

  if ($stmtProcedimento === false) {
      die("Erro ao preparar a consulta SQL: " . mysqli_error($connMedical) . "\n");
  }
  
  mysqli_stmt_bind_param($stmtProcedimento, 'iiissiis', $newId, $id_PacienteSql, $id_ProfissionalSql, $dh_inicio, $dh_fim, $id_convenioSql, $id_ProcedimentoSql, $observacoes);

  if (mysqli_stmt_execute($stmtProcedimento)) {
    echo "Registro inserido na tabela procedimentos com sucesso!!.\n";

  }else{
    echo "Erro ao inserir dados: " . mysqli_stmt_error($stmtProcedimento) . "\n";

  }

  mysqli_stmt_close($stmtProcedimento);

}

// Encerrando as conexões:
$connMedical->close();
fclose($arquivo);
//$connTemp->close();

// Informações de Fim da Migração:
echo "Fim da Migração: " . dateNow() . ".\n";