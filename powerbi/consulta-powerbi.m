let
    ApiKey = Text.Trim(Text.From(ApiKeyLeme)),
    Fonte = Json.Document(
        Web.Contents(
            "https://lemeinformatica.com.br",
            [
                RelativePath = "powerbi.php",
                Headers = [Accept = "application/json", #"X-API-KEY" = ApiKey],
                Timeout = #duration(0, 0, 2, 0)
            ]
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
