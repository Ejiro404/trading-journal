// NXLOG_Sync.mq5
#property strict

input string WebhookURL = "http://YOUR-DOMAIN.com/trading-journal/api/mt5/trade-hook.php";

/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
| Paste your NXLOG sync token below.
|
| Get your token from:
| NXLOG → Log → Connect MT5
|--------------------------------------------------------------------------
*/

input string SyncToken = "PASTE_YOUR_SYNC_TOKEN_HERE";

bool PostToNXLOG(string json, string label)
{
   char data[];
   char result[];
   string result_headers;

   StringToCharArray(json, data, 0, StringLen(json), CP_UTF8);

   string headers =
      "Content-Type: application/json\r\n";

   ResetLastError();

   int res = WebRequest(
      "POST",
      WebhookURL,
      headers,
      5000,
      data,
      result,
      result_headers
   );

   string response = CharArrayToString(result, 0, -1, CP_UTF8);

   Print("NXLOG ", label, " SEND: ", json);
   Print("NXLOG ", label, " HTTP: ", res,
         " RESPONSE: ", response,
         " LASTERROR: ", GetLastError());

   return (res == 200);
}

bool SendTradeOpen(
   ulong position_id,
   ulong deal_ticket,
   string symbol,
   string direction,
   double price,
   datetime entryTime,
   double volume
)
{
   string json = StringFormat(
      "{\"sync_token\":\"%s\",\"event\":\"open\",\"ticket\":\"%I64u\",\"position_id\":\"%I64u\",\"deal_id\":\"%I64u\",\"symbol\":\"%s\",\"direction\":\"%s\",\"entry_time\":\"%s\",\"entry_price\":%.8f,\"volume\":%.2f}",
      SyncToken,
      position_id,
      position_id,
      deal_ticket,
      symbol,
      direction,
      TimeToString(entryTime, TIME_DATE | TIME_SECONDS),
      price,
      volume
   );

   return PostToNXLOG(json, "OPEN");
}

bool SendTradeClose(
   ulong position_id,
   ulong deal_ticket,
   string symbol,
   string direction,
   double exitPrice,
   datetime exitTime,
   double profit,
   double volume
)
{
   string json = StringFormat(
      "{\"sync_token\":\"%s\",\"event\":\"close\",\"ticket\":\"%I64u\",\"position_id\":\"%I64u\",\"deal_id\":\"%I64u\",\"symbol\":\"%s\",\"direction\":\"%s\",\"exit_time\":\"%s\",\"exit_price\":%.8f,\"pnl_amount\":%.2f,\"volume\":%.2f}",
      SyncToken,
      position_id,
      position_id,
      deal_ticket,
      symbol,
      direction,
      TimeToString(exitTime, TIME_DATE | TIME_SECONDS),
      exitPrice,
      profit,
      volume
   );

   return PostToNXLOG(json, "CLOSE");
}

int OnInit()
{
   Print("NXLOG LIVE: initialized");
   Print("NXLOG Webhook URL: ", WebhookURL);

   if(SyncToken == "PASTE_YOUR_SYNC_TOKEN_HERE")
   {
      Print("NXLOG ERROR: Please paste your SyncToken into EA settings.");
   }

   return(INIT_SUCCEEDED);
}

void OnTradeTransaction(
   const MqlTradeTransaction &trans,
   const MqlTradeRequest &request,
   const MqlTradeResult &result
)
{
   if(trans.type != TRADE_TRANSACTION_DEAL_ADD)
      return;

   ulong deal_ticket = trans.deal;

   if(deal_ticket == 0)
      return;

   if(!HistoryDealSelect(deal_ticket))
   {
      Print("NXLOG: failed to select deal ", deal_ticket);
      return;
   }

   string symbol = HistoryDealGetString(deal_ticket, DEAL_SYMBOL);

   long dealType  = HistoryDealGetInteger(deal_ticket, DEAL_TYPE);
   long entryType = HistoryDealGetInteger(deal_ticket, DEAL_ENTRY);

   double price  = HistoryDealGetDouble(deal_ticket, DEAL_PRICE);
   double profit = HistoryDealGetDouble(deal_ticket, DEAL_PROFIT);
   double volume = HistoryDealGetDouble(deal_ticket, DEAL_VOLUME);

   datetime dealTime =
      (datetime)HistoryDealGetInteger(deal_ticket, DEAL_TIME);

   ulong position_id =
      (ulong)HistoryDealGetInteger(deal_ticket, DEAL_POSITION_ID);

   string direction = "";

   if(dealType == DEAL_TYPE_BUY)
      direction = "BUY";
   else if(dealType == DEAL_TYPE_SELL)
      direction = "SELL";
   else
      return;

   // OPEN
   if(entryType == DEAL_ENTRY_IN)
   {
      SendTradeOpen(
         position_id,
         deal_ticket,
         symbol,
         direction,
         price,
         dealTime,
         volume
      );

      return;
   }

   // CLOSE
   if(entryType == DEAL_ENTRY_OUT)
   {
      SendTradeClose(
         position_id,
         deal_ticket,
         symbol,
         direction,
         price,
         dealTime,
         profit,
         volume
      );

      return;
   }
}