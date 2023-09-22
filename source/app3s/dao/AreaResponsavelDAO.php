<?php

/**
 * Classe feita para manipulação do objeto AreaResponsavel
 * feita automaticamente com programa gerador de software inventado por
 * @author Jefferson Uchôa Ponte
 */

namespace app3s\dao;

use PDO;
use PDOException;
use app3s\model\AreaResponsavel;

class AreaResponsavelDAO extends DAO
{



	/**
	 * @deprecated 1.0.17
	 */
    public function fillById(AreaResponsavel $areaResponsavel)
    {

        $id = $areaResponsavel->getId();
        $sql = "SELECT area_responsavel.id, area_responsavel.nome, area_responsavel.descricao, area_responsavel.email FROM area_responsavel
                WHERE area_responsavel.id = :id
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
                $areaResponsavel->setId($row['id']);
                $areaResponsavel->setNome($row['nome']);
                $areaResponsavel->setDescricao($row['descricao']);
                $areaResponsavel->setEmail($row['email']);
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
        return $areaResponsavel;
    }
}
