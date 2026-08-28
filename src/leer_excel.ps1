param(
    [Parameter(Mandatory = $true)]
    [string]$Path
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $Path)) {
    Write-Output '{"ok":false,"error":"Archivo no encontrado"}'
    exit 1
}

try {
    $connStr = "Provider=Microsoft.ACE.OLEDB.12.0;Data Source=$Path;Extended Properties='Excel 12.0 Xml;HDR=YES';"
    $conn = New-Object System.Data.OleDb.OleDbConnection $connStr
    $conn.Open()
    $schema = $conn.GetOleDbSchemaTable([System.Data.OleDb.OleDbSchemaGuid]::Tables, $null)
    $sheet = ($schema | Where-Object { $_.TABLE_NAME -like '*$' } | Select-Object -First 1).TABLE_NAME
    if (-not $sheet) {
        throw 'No se encontro hoja en el archivo Excel'
    }

    $cmd = $conn.CreateCommand()
    $cmd.CommandText = "SELECT * FROM [$sheet]"
    $adapter = New-Object System.Data.OleDb.OleDbDataAdapter $cmd
    $table = New-Object System.Data.DataTable
    [void]$adapter.Fill($table)
    $conn.Close()

    $headers = @()
    foreach ($col in $table.Columns) {
        $headers += [string]$col.ColumnName
    }

    $rows = New-Object System.Collections.Generic.List[object]
    foreach ($dataRow in $table.Rows) {
        $item = @{}
        foreach ($col in $table.Columns) {
            $value = $dataRow[$col]
            if ($null -eq $value) {
                $item[[string]$col.ColumnName] = ''
            } else {
                $item[[string]$col.ColumnName] = [string]$value
            }
        }
        $rows.Add($item)
    }

    $payload = [ordered]@{
        ok = $true
        sheet = $sheet
        headers = $headers
        rows = $rows
    }

    $json = $payload | ConvertTo-Json -Depth 6 -Compress
    [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
    Write-Output $json
} catch {
    $msg = $_.Exception.Message -replace '"', "'"
    Write-Output ('{"ok":false,"error":"' + $msg + '"}')
    exit 1
}
