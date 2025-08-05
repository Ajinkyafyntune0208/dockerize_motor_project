import { useEffect, useMemo, useState } from "react";
import { ShareQuote } from "modules/Home/home.slice";
import { debounce } from "lodash";
import { useDispatch } from "react-redux";
import swal from "sweetalert";

export const useAceLeadSMS = (
  temp_data,
  CardData,
  owner,
  mobileNoLead,
  enquiry_id,
  dispatch
) => {
  //ACE lead SMS

  const [memonisedNo, setMemonisedNo] = useState(false);
  useEffect(() => {
    if (
      import.meta.env.VITE_BROKER === "ACE" &&
      mobileNoLead &&
      mobileNoLead.length === 10 &&
      memonisedNo * 1 !== mobileNoLead * 1 &&
      CardData?.owner?.mobileNumber * 1 !== mobileNoLead * 1 &&
      owner?.mobileNumber * 1 !== mobileNoLead * 1
    ) {
      setMemonisedNo(mobileNoLead);
      dispatch(
        ShareQuote({
          enquiryId: enquiry_id,
          notificationType: "sms",
          domain: `http://${window.location.hostname}`,
          type: "Aceleadsms",
          mobileNo: mobileNoLead,
          productName: temp_data?.selectedQuote?.productName,
        })
      );
    }
  }, [mobileNoLead]);
};