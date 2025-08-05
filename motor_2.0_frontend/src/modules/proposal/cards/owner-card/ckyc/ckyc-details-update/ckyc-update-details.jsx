import Popup from "components/Popup/Popup";
import SummaryProposal from "../../../../summary/summary-proposal";
import _ from "lodash";
import { useDispatch } from "react-redux";
import { clear } from "../../../../proposal.slice";
import { Button as Btn } from "react-bootstrap";
import swal from 'sweetalert';

export const CkycUpdatedDetails = ({
  temp_data,
  tempOwner,
  customerDetails,
  setVerifiedData,
  setValue,
  gender,
  setRadioValue,
  setisCkycDetailsRejected,
  setShow,
  show,
  setuploadFile,
  setckycValue,
  fields,
  lessthan768,
  setResubmit,
}) => {
  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  const dispatch = useDispatch();
  //sbi popup for customer detail confirmation
  const setValueCustomerDetails = () => {
    Object.keys(tempOwner)?.forEach((each) => {
      setValue(each, tempOwner[each]);
    });
    //verified data consists keys that should not be editable on ckyc verification
    customerDetails && setVerifiedData(Object.keys(customerDetails));
    customerDetails &&
      Object.keys(customerDetails)?.forEach((each) => {
        customerDetails[each] && setValue(each, customerDetails[each]);
      });
    //Gender Index check
    customerDetails?.gender &&
      !_.isEmpty(
        _.compact(
          gender?.map((item) =>
            item?.code === customerDetails?.gender ? item : ""
          )
        )
      ) &&
      setRadioValue(
        _.compact(
          gender?.map((item) =>
            item?.code === customerDetails?.gender ? item : ""
          )
        )[0]?.code
      );
    if (
      customerDetails &&
      (Object.keys(customerDetails)?.includes("addressLine1") ||
        Object.keys(customerDetails)?.includes("addressLine2") ||
        Object.keys(customerDetails)?.includes("addressLine3"))
    ) {
      setValue(
        "address",
        `${customerDetails?.addressLine1 ? customerDetails?.addressLine1 : ""}${
          customerDetails?.addressLine2
            ? ` ${customerDetails?.addressLine2}`
            : ""
        }${
          customerDetails?.addressLine3
            ? ` ${customerDetails?.addressLine3}`
            : ""
        }`
      );
      setValue("isCkycDetailsRejected", "N");

      setVerifiedData([...Object.keys(customerDetails), "address"]);
    }
    swal(
      "Success",
      "CKYC verified. Please proceed to continue",
      "success"
    ).then(() => {
      dispatch(clear("verifyCkycnum"));
    });
    setResubmit(true);
  };

  const confirmHandler = () => {
    setisCkycDetailsRejected(false);
    setValueCustomerDetails();
    setShow(false);
  };

  const rejectHandler = () => {
    setisCkycDetailsRejected(true);
    setShow(false);
    swal("", "Please upload required documents", "info");
    setuploadFile(true);
    setckycValue("NO");
  };

  const onClose = () => {
    setShow(false);
    companyAlias !== "sbi" && dispatch(clear("verifyCkycnum"));
  };

  const content = (
    <>
      {companyAlias === "sbi" ? (
        <div
          style={{ padding: lessthan768 ? "25px 5px 5px 5px" : "25px 30px" }}
        >
          <p
            className="mt-1"
            style={{
              fontSize: "16px",
              fontWeight: "600",
              margin: "10px 0 0 20px",
            }}
          >
            Action Required
          </p>
          <div className="p-2 m-1">
            <div>
              <SummaryProposal
                popup={true}
                data={customerDetails}
                lessthan768={lessthan768}
                fields={fields}
                type="header"
                isOrganizationSummary={true}
              />
            </div>
            <div
              className="w-100 d-flex justify-content-end mt-3"
              style={{ paddingRight: lessthan768 ? "15px" : "" }}
            >
              <Btn
                className="mx-2"
                size="sm"
                variant="success"
                onClick={confirmHandler}
              >
                Use this data
              </Btn>
              <Btn size="sm" variant="danger" onClick={rejectHandler}>
                Discard CKYC
              </Btn>
            </div>
          </div>
        </div>
      ) : (
        <div
          style={{ padding: lessthan768 ? "25px 5px 5px 5px" : "25px 30px" }}
        >
          <p
            className="mt-1"
            style={{
              fontSize: "16px",
              fontWeight: "600",
              margin: "10px 0 0 20px",
            }}
          >
            Please review the data we have fetched after CKYC verification.
          </p>
          <div className="p-2 m-1">
            <div>
              <SummaryProposal
                popup={true}
                data={customerDetails}
                lessthan768={lessthan768}
                fields={fields}
                type="header"
              />
            </div>
            <div
              className="w-100 d-flex justify-content-end mt-3"
              style={{ paddingRight: lessthan768 ? "15px" : "" }}
            >
              <Btn size="sm" variant="success" onClick={() => setShow(false)}>
                Okay
              </Btn>
            </div>
          </div>
        </div>
      )}
    </>
  );

  return (
    <>
      <Popup
        top="40%"
        show={show}
        content={content}
        onClose={onClose}
        position="middle"
        height="auto"
        width="600px"
      />
    </>
  );
};
