/**
 * ============================================================
 * PROVisit Calendar Engine v1.4
 * ============================================================
 *
 * Compatible:
 * - VISIT_DB
 * - USER_MASTER
 * - CALENDAR_MASTER
 * - CALENDAR_EVENT_LOG
 *
 * Architecture:
 *
 * VISIT
 *    |
 *    +---- Management Calendar
 *    |
 *    +---- Team Calendar (YES/MOU)
 *
 * CALENDAR_EVENT_LOG menjadi sumber tracking event.
 *
 * ============================================================
 */


/**
 * ============================================================
 * MAIN SYNC FUNCTION
 * ============================================================
 */


function syncVisitToCalendar(visitId) {


  const visit =
    getVisitById_(visitId);


  if (!visit) {

    throw new Error(
      "Visit tidak ditemukan: " + visitId
    );

  }


  /**
   * hanya sync Planned dan Confirmed
   */

  if (
    visit.Status !== "Planned" &&
    visit.Status !== "Confirmed"
  ) {

    Logger.log(
      "SKIP STATUS : " +
      visit.Status
    );

    return;

  }


  try {


    /**
     * MANAGEMENT CALENDAR
     */

    const managementCalendar =
      getCalendarByType_("MANAGEMENT");


    if (managementCalendar) {

      createCalendarEventIfNeeded_(
        visit,
        managementCalendar
      );

    }



    /**
     * TEAM CALENDAR
     */

    const team =
      getSalesTeam_(
        visit.Primary_Sales_User_ID
      );


    if (team) {


      const teamCalendar =
        getCalendarByTeam_(team);


      if (teamCalendar) {


        createCalendarEventIfNeeded_(
          visit,
          teamCalendar
        );


      }


    }



    updateCalendarStatus_(
      visit.Visit_ID,
      "SUCCESS",
      ""
    );


  }


  catch(error){


    updateCalendarStatus_(
      visit.Visit_ID,
      "FAILED",
      error.message
    );


    throw error;

  }


}






/**
 * ============================================================
 * CREATE EVENT WITH DUPLICATE CHECK
 * ============================================================
 */


function createCalendarEventIfNeeded_(
  visit,
  calendarConfig
){


  /**
   * cek apakah event sudah ada
   */

  const existing =
    getExistingCalendarEvent_(
      visit.Visit_ID,
      calendarConfig.Calendar_ID
    );



  if(existing){

    Logger.log(
      "EXISTING EVENT : " +
      visit.Visit_ID +
      " | " +
      calendarConfig.Calendar_Name
    );

    updateCalendarEvent_(
      existing.eventId,
      visit,
      calendarConfig
    );

    return existing.eventId;

  }




  const calendar =
    CalendarApp
    .getCalendarById(
      calendarConfig.Google_Calendar_ID
    );



  if(!calendar){

    throw new Error(
      "Calendar tidak ditemukan : " +
      calendarConfig.Google_Calendar_ID
    );

  }




  const start =
    buildStartDateTime_(
      visit
    );



  const duration = 60;



  const end =
    new Date(
      start.getTime()
      +
      duration * 60000
    );




  const title =
    "[PROVisit] " +
    visit.Visit_Title;



  const description =
`
PROVisit

Visit ID:
${visit.Visit_ID}

Customer:
${visit.Customer_Name_Snapshot}

PIC Customer:
${visit.PIC_Customer || "-"}

Sales:
${visit.Primary_Sales_User_ID}

Purpose:
${visit.Visit_Purpose}

Status:
${visit.Status}
`;





  const event =
    calendar.createEvent(
      title,
      start,
      end,
      {
        description:
        description
      }
    );




  logCalendarEvent_(
    visit.Visit_ID,
    calendarConfig.Calendar_ID,
    "CREATE",
    event.getId(),
    "SUCCESS",
    "",
    title,
    start,
    end,
    "ACTIVE"
);



  Logger.log(
    "CREATE SUCCESS : " +
    visit.Visit_ID +
    " -> " +
    calendarConfig.Calendar_Name
  );



  return event;


}







/**
 * ============================================================
 * UPDATE EXISTING CALENDAR EVENT
 * ============================================================
 */

function updateCalendarEvent_(
  eventId,
  visit,
  calendarConfig
){

  const calendar =
    CalendarApp.getCalendarById(
      calendarConfig.Google_Calendar_ID
    );

  if(!calendar){
    throw new Error(
      "Calendar tidak ditemukan"
    );
  }

  const event =
    calendar.getEventById(eventId);

  if(!event){
    throw new Error(
      "Event tidak ditemukan: " + eventId
    );
  }

  const start =
    buildStartDateTime_(visit);

  const end =
    new Date(
      start.getTime() + 60 * 60000
    );

  const title =
    "[PROVisit] " + visit.Visit_Title;


  event.setTitle(title);
  event.setTime(start, end);

  event.setDescription(
`PROVisit

Visit ID:
${visit.Visit_ID}

Customer:
${visit.Customer_Name_Snapshot}

PIC Customer:
${visit.PIC_Customer || "-"}

Sales:
${visit.Primary_Sales_User_ID}

Purpose:
${visit.Visit_Purpose}

Status:
${visit.Status}`
  );


  logCalendarEvent_(
    visit.Visit_ID,
    calendarConfig.Calendar_ID,
    "CREATE",
    event.getId(),
    "SUCCESS",
    "",
    title,
    start,
    end,
    "ACTIVE"
);

}



/**
 * ============================================================
 * CHECK EXISTING EVENT
 * ============================================================
 */


function getExistingCalendarEvent_(
  visitId,
  calendarId
){


  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "CALENDAR_EVENT_LOG"
    );



  if(!sheet){

    return null;

  }



  const data =
    sheet
    .getDataRange()
    .getValues();



  if(data.length <= 1){

    return null;

  }



  const headers =
    data[0];



  const visitCol =
    headers.indexOf(
      "Visit_ID"
    );


  const calCol =
    headers.indexOf(
      "Calendar_ID"
    );


  const eventCol =
    headers.indexOf(
      "Calendar_Event_ID"
    );


  const statusCol =
    headers.indexOf(
      "Sync_Status"
    );




  for(
    let i = 1;
    i < data.length;
    i++
  ){


    if(
      data[i][visitCol] === visitId &&
      data[i][calCol] === calendarId &&
      data[i][statusCol] === "SUCCESS"
    ){

      return {
        eventId: data[i][eventCol],
        eventTitle: data[i][headers.indexOf("Event_Title_Snapshot")],
        eventStart: data[i][headers.indexOf("Event_Start_Snapshot")],
        eventEnd: data[i][headers.indexOf("Event_End_Snapshot")]
      };

    }


  }



  return null;


}

/**
 * ============================================================
 * DATABASE HELPER
 * ============================================================
 */


/**
 * GET VISIT DATA
 */

function getVisitById_(visitId){


  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "VISIT_DB"
    );



  const data =
    sheet
    .getDataRange()
    .getValues();



  const headers =
    data[0];



  const idCol =
    headers.indexOf(
      "Visit_ID"
    );



  for(
    let i = 1;
    i < data.length;
    i++
  ){


    if(
      data[i][idCol] === visitId
    ){


      const obj = {};


      headers.forEach(
        function(header,index){

          obj[header] =
            data[i][index];

        }
      );


      return obj;


    }


  }



  return null;


}







/**
 * ============================================================
 * USER MASTER ROUTING
 *
 * Input:
 * USR-0007
 *
 * Output:
 * YES
 *
 * Support:
 * User_ID
 * Initial
 *
 * ============================================================
 */


function getSalesTeam_(userKey){


  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "USER_MASTER"
    );



  const data =
    sheet
    .getDataRange()
    .getValues();



  const headers =
    data.shift();



  const userCol =
    headers.indexOf(
      "User_ID"
    );


  const initialCol =
    headers.indexOf(
      "Initial"
    );


  const teamCol =
    headers.indexOf(
      "Team"
    );




  for(
    let i = 0;
    i < data.length;
    i++
  ){


    const row =
      data[i];



    if(
      row[userCol] === userKey ||
      row[initialCol] === userKey
    ){


      return row[teamCol];


    }


  }



  return null;


}






/**
 * ============================================================
 * CALENDAR MASTER
 * ============================================================
 */


function getCalendarMaster_(){


  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "CALENDAR_MASTER"
    );



  const data =
    sheet
    .getDataRange()
    .getValues();



  const headers =
    data[0];



  const activeCol =
    headers.indexOf(
      "Active"
    );



  return data
    .slice(1)
    .filter(
      function(row){

        return (
          row[activeCol] === true ||
          String(row[activeCol])
          .toUpperCase()
          === "TRUE"
        );

      }
    )
    .map(
      function(row){


        const obj = {};


        headers.forEach(
          function(header,index){

            obj[header] =
              row[index];

          }
        );


        return obj;


      }
    );

}





function getCalendarByType_(type){


  return getCalendarMaster_()
    .find(
      function(calendar){

        return (
          calendar.Calendar_Type
          ===
          type
        );

      }
    );


}





function getCalendarByTeam_(team){


  return getCalendarMaster_()
    .find(
      function(calendar){

        return (

          calendar.Calendar_Type
          ===
          "TEAM"

          &&

          calendar.Team
          ===
          team

        );

      }
    );


}








/**
 * ============================================================
 * CALENDAR EVENT LOG
 * ============================================================
 */



function logCalendarEvent_(
  visitId,
  calendarId,
  action,
  eventId,
  syncStatus,
  errorMessage,
  eventTitle,
  eventStart,
  eventEnd,
  eventStatus
) {

  const sheet =
    SpreadsheetApp
      .getActive()
      .getSheetByName("CALENDAR_EVENT_LOG");


  const now = new Date();


  sheet.appendRow([

    Utilities.getUuid(),       // Calendar_Log_ID

    visitId,                   // Visit_ID

    calendarId,                // Calendar_ID

    action,                    // Action

    eventId,                   // Calendar_Event_ID

    syncStatus,                // Sync_Status

    errorMessage || "",        // Error_Message

    now,                       // Created_At

    now,                       // Updated_At

    eventStatus || "ACTIVE",   // Event_Status

    eventTitle || "",          // Event_Title_Snapshot

    eventStart || "",          // Event_Start_Snapshot

    eventEnd || ""              // Event_End_Snapshot

  ]);

}



/**
 * ============================================================
 * UPDATE VISIT SYNC STATUS
 * ============================================================
 */


function updateCalendarStatus_(
  visitId,
  status,
  errorMessage
){


  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "VISIT_DB"
    );



  const data =
    sheet
    .getDataRange()
    .getValues();



  const headers =
    data[0];



  const visitCol =
    headers.indexOf(
      "Visit_ID"
    );


  const statusCol =
    headers.indexOf(
      "Calendar_Sync_Status"
    );


  const errorCol =
    headers.indexOf(
      "Calendar_Sync_Error"
    );





  for(
    let i = 1;
    i < data.length;
    i++
  ){


    if(
      data[i][visitCol]
      ===
      visitId
    ){


      if(statusCol !== -1){

        sheet
        .getRange(
          i+1,
          statusCol+1
        )
        .setValue(
          status
        );

      }



      if(errorCol !== -1){

        sheet
        .getRange(
          i+1,
          errorCol+1
        )
        .setValue(
          errorMessage
        );

      }



      return;


    }


  }


}

/**
 * ============================================================
 * DATE TIME BUILDER
 * ============================================================
 *
 * Support:
 * - Google Sheet Date Object
 * - Google Sheet Serial Number
 * - dd/MM/yyyy
 * - yyyy-MM-dd
 *
 * Time:
 * - HH:mm
 * - Date Object
 *
 * Timezone:
 * Asia/Jakarta
 *
 * ============================================================
 */


function buildStartDateTime_(visit){


  let rawDate =
    visit.Visit_Date;


  let date;



  /**
   * Google Sheet Date Object
   */

  if(rawDate instanceof Date){


    date =
      new Date(
        rawDate.getFullYear(),
        rawDate.getMonth(),
        rawDate.getDate()
      );


  }


  /**
   * Google Sheet Serial Number
   */

  else if(
    typeof rawDate === "number"
  ){


    date =
      new Date(
        1899,
        11,
        30 + rawDate
      );


  }


  /**
   * String Date
   */

  else{


    const value =
      String(rawDate);



    if(
      value.indexOf("/") !== -1
    ){


      const parts =
        value.split("/");



      date =
        new Date(
          Number(parts[2]),
          Number(parts[1])-1,
          Number(parts[0])
        );


    }
    else{


      date =
        new Date(value);


    }


  }





  if(
    isNaN(date.getTime())
  ){

    throw new Error(
      "Format tanggal tidak valid: "
      +
      rawDate
    );

  }






  /**
   * Parse Start Time
   */


  let hour = 0;

  let minute = 0;



  if(
    visit.Start_Time instanceof Date
  ){


    hour =
      visit.Start_Time.getHours();


    minute =
      visit.Start_Time.getMinutes();


  }


  else{


    const time =
      String(
        visit.Start_Time
      )
      .split(":");



    hour =
      Number(time[0])
      ||
      0;



    minute =
      Number(time[1])
      ||
      0;


  }





  date.setHours(
    hour,
    minute,
    0,
    0
  );



  return date;


}







/**
 * ============================================================
 * BULK SYNC
 * ============================================================
 *
 * Sync all Planned & Confirmed visit
 *
 * ============================================================
 */


function syncAllActiveVisits(){


  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "VISIT_DB"
    );



  const data =
    sheet
    .getDataRange()
    .getValues();



  const headers =
    data[0];



  const visitCol =
    headers.indexOf(
      "Visit_ID"
    );


  const statusCol =
    headers.indexOf(
      "Status"
    );



  let success = 0;

  let skipped = 0;

  let failed = 0;





  for(
    let i = 1;
    i < data.length;
    i++
  ){


    const visitId =
      data[i][visitCol];



    const status =
      data[i][statusCol];





    if(
      status !== "Planned"
      &&
      status !== "Confirmed"
    ){


      skipped++;

      continue;


    }





    try{


      syncVisitToCalendar(
        visitId
      );


      success++;


      Logger.log(
        "SYNC SUCCESS : "
        +
        visitId
      );


    }


    catch(error){


      failed++;


      Logger.log(
        "SYNC FAILED : "
        +
        visitId
        +
        " | "
        +
        error.message
      );


    }


  }





  Logger.log(
`
================================

Calendar Sync Summary

Success : ${success}
Skipped : ${skipped}
Failed  : ${failed}

================================
`
  );


}








/**
 * ============================================================
 * TEST FUNCTION
 * ============================================================
 */


function testCalendarSync(){


  syncVisitToCalendar(
    "VST-2026-000002"
  );


}








/**
 * ============================================================
 * DEBUG FUNCTIONS
 * ============================================================
 */


function debugVisitTeamRouting(){


  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "VISIT_DB"
    );



  const data =
    sheet
    .getDataRange()
    .getValues();



  const headers =
    data[0];



  const visitCol =
    headers.indexOf(
      "Visit_ID"
    );


  const salesCol =
    headers.indexOf(
      "Primary_Sales_User_ID"
    );



  for(
    let i = 1;
    i < data.length;
    i++
  ){


    Logger.log(

      data[i][visitCol]
      +
      " | "
      +
      data[i][salesCol]
      +
      " | TEAM = "
      +
      getSalesTeam_(
        data[i][salesCol]
      )

    );


  }


}







function debugCalendarRouting(){


  const calendars =
    getCalendarMaster_();



  calendars.forEach(
    function(calendar){


      Logger.log(

        calendar.Calendar_ID
        +
        " | "
        +
        calendar.Calendar_Name
        +
        " | TYPE="
        +
        calendar.Calendar_Type
        +
        " | TEAM="
        +
        calendar.Team
        +
        " | GOOGLE_ID="
        +
        calendar.Google_Calendar_ID

      );


    }
  );


}





function debugCalendarLog(){


  const sheet =
    SpreadsheetApp
    .getActive()
    .getSheetByName(
      "CALENDAR_EVENT_LOG"
    );



  const data =
    sheet
    .getDataRange()
    .getValues();



  data.forEach(
    function(row){

      Logger.log(
        row.join(" | ")
      );

    }
  );


}