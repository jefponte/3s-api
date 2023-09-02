<?php

/**
 * Classe feita para manipulação do objeto Usuario
 * feita automaticamente com programa gerador de software inventado por
 * @author Jefferson Uchôa Ponte
 */

namespace app3s\dao;

use PDO;
use PDOException;
use app3s\model\Usuario;
use app3s\util\Sessao;
use App\Models\User;

class UsuarioDAO extends DAO
{


    public function fillById(Usuario $usuario)
    {

        $id = $usuario->getId();
        $sql = "SELECT usuario.id, usuario.name as nome, usuario.email, usuario.login,
        usuario.role as nivel, usuario.division_id as id_setor FROM users as usuario
                WHERE usuario.id = :id
                 LIMIT 1000";

        try {
            $stmt = $this->connection->prepare($sql);

            if (!$stmt) {
                echo "<br>Mensagem de erro retornada: " . $this->connection->errorInfo()[2] . "<br>";
            }
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $row) {
                $usuario->setId($row['id']);
                $usuario->setNome($row['nome']);
                $usuario->setEmail($row['email']);
                $usuario->setLogin($row['login']);
                $usuario->setNivel($row['nivel']);
                $usuario->setIdSetor($row['id_setor']);
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
        return $usuario;
    }
}
