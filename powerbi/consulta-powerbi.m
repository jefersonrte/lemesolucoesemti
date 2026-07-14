let
    Fonte = Json.Document(
        Web.Contents(
            "https://lemeinformatica.com.br/estacio/final/api/powerbi.php",
            [Headers = [#"X-API-KEY" = "1b928d1b3a009d037649cc5a87d2bd4042ad4bf330970a8b7815dce03ee08885"]]
        )
    ),
    Dados = Fonte[data],
    Tabela = Table.FromRecords(Dados),
    Tipos = Table.TransformColumnTypes(
        Tabela,
        {
            {"id", Int64.Type},
            {"nome", type text},
            {"raca", type text},
            {"porte", type text}
        }
    )
in
    Tipos
