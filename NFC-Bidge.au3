; ============================================
; NSCCN Smart Reader -> HRMS Online NFC Bridge
; Sends scanned UID to Laravel online attendance bridge
; ============================================

Opt("WinTitleMatchMode", 2)

Global $g_sAppPath  = "C:\xampp\htdocs\hrms\nfcPro_x64.exe"
Global $g_sWinTitle = "wCopy NFC Reader Read and Write Tool"
Global $g_sApiUrl   = "https://hrms.rodagregado.online/api/nfc/receive"

Global $g_sLastUID       = ""
Global $g_hLastSendTimer = TimerInit()
Global $g_iCooldownMs    = 3000

MsgBox(64, "NFC Bridge", "Online NFC bridge started.`nClick OK and it will attach to WCopy.")

If Not ProcessExists("nfcPro_x64.exe") Then
    Run('"' & $g_sAppPath & '"')
EndIf

If Not WinWait($g_sWinTitle, "", 10) Then
    MsgBox(16, "NFC Bridge", "Could not find WCopy window.`nI looked for title:`n" & $g_sWinTitle)
    Exit
EndIf

TrayTip("NFC Bridge", "Online attendance bridge running.", 3)

While 1
    If Not WinExists($g_sWinTitle) Then
        If Not ProcessExists("nfcPro_x64.exe") Then
            Run('"' & $g_sAppPath & '"')
        EndIf
        WinWait($g_sWinTitle, "", 10)
    EndIf

    ControlClick($g_sWinTitle, "", "Button95")
    Sleep(500)

    Local $sCardText = ControlGetText($g_sWinTitle, "", "RichEdit20W5")
    If $sCardText = "" Then
        Sleep(500)
        ContinueLoop
    EndIf

    Local $iAtqaPos = StringInStr($sCardText, "atqa", 2)
    Local $sUIDPart = ""

    If $iAtqaPos > 0 Then
        $sUIDPart = StringLeft($sCardText, $iAtqaPos - 1)
    Else
        $sUIDPart = $sCardText
    EndIf

    $sUIDPart = StringStripWS($sUIDPart, 7)
    If $sUIDPart = "" Then
        Sleep(500)
        ContinueLoop
    EndIf

    Local $sUID = StringUpper(StringReplace($sUIDPart, " ", ""))
    If $sUID = "" Then
        Sleep(400)
        ContinueLoop
    EndIf

    Local $iElapsed = TimerDiff($g_hLastSendTimer)
    If $sUID = $g_sLastUID And $iElapsed < $g_iCooldownMs Then
        Sleep(300)
        ContinueLoop
    EndIf

    $g_sLastUID = $sUID
    $g_hLastSendTimer = TimerInit()

    TrayTip("NFC UID", "Card UID: " & $sUID, 2)
    _SendUIDToApi($sUID)

    Sleep(400)
WEnd

Func _SendUIDToApi($sUID)
    Local $oHTTP = ObjCreate("WinHttp.WinHttpRequest.5.1")
    If Not IsObj($oHTTP) Then
        TrayTip("NFC Bridge", "WinHttp COM object not available.", 3)
        Return
    EndIf

    Local $sData = "nfc_uid=" & _UrlEncode($sUID)

    $oHTTP.Open("POST", $g_sApiUrl, False)
    $oHTTP.SetRequestHeader("Content-Type", "application/x-www-form-urlencoded")
    $oHTTP.SetRequestHeader("Accept", "application/json")
    $oHTTP.SetRequestHeader("User-Agent", "NSCCN-NFC-Bridge/1.0")

    $oHTTP.Send($sData)

    Local $iStatus = $oHTTP.Status
    If $iStatus <> 200 Then
        TrayTip("NFC Bridge", "API HTTP " & $iStatus, 3)
    EndIf
EndFunc

Func _UrlEncode($sData)
    Local $sRet = ""
    For $i = 1 To StringLen($sData)
        Local $sChar = StringMid($sData, $i, 1)
        Local $iAsc = Asc($sChar)
        If ($iAsc >= 48 And $iAsc <= 57) Or _
           ($iAsc >= 65 And $iAsc <= 90) Or _
           ($iAsc >= 97 And $iAsc <= 122) Or _
           $sChar = "-" Or $sChar = "_" Or $sChar = "." Or $sChar = "~" Then
            $sRet &= $sChar
        Else
            $sRet &= "%" & Hex($iAsc, 2)
        EndIf
    Next
    Return $sRet
EndFunc
