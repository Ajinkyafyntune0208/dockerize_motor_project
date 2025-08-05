import React, { useState, useEffect } from "react";
import PropTypes from "prop-types";
import { Row } from "react-bootstrap";
import { useForm } from "react-hook-form";
import { useDispatch, useSelector } from "react-redux";
import tooltip from "../../../../assets/img/tooltip.svg";
import CustomTooltip from "components/tooltip/CustomTooltip";
import Popup from "components/Popup/Popup";
import "../../quotesPopup/idvPopup/idvPopup.scss";
import { set_temp_data } from "modules/Home/home.slice";
import { useMediaPredicate } from "react-media-hook";
import Drawer from "@mui/material/Drawer";
import _ from "lodash";
import { CancelAll } from "modules/quotesPage/quote.slice";
import moment from "moment";
import { differenceInDays } from "date-fns";
import { toDate, _haptics } from "utils";
//prettier-ignore
import { GlobalStyle, Conatiner, PaymentTermTitle, PopupSubTitle,
        ApplyButton, PaymentTermRadioWrap, MobileDrawerBody,
        CloseButton,
       } from "./styles";
import VehicleOwnership from "./VehicleOwnership";
import ClaimsMade from "./ClaimsMade";
import EligibilityMessage from "./EligibilityMessage";
import ExistingNcb from "./ExistingNcb";
const NCBPopup = ({ show, onClose, ncb, setNcb }) => {
  const lessthan963 = useMediaPredicate("(max-width: 963px)");
  const lessthan450 = useMediaPredicate("(max-width: 450px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");

  const dispatch = useDispatch();
  const { ncbList, tempData } = useSelector((state) => state.quoteFilter);
  const { temp_data } = useSelector((state) => state.home);

  const { register, watch, setValue } = useForm();
  const expPolicy = watch("claimMade");
  const ncbValue = watch("existinNcb");
  const OwnerShip = watch("ownerShip");

  const [ncbState, setNcbState] = useState(ncbValue || temp_data?.ncb);
  const [noClaim, setNoClaim] = useState(false);
  const [drawer, setDrawer] = useState(false);
  const myOrderedNcbList = _.sortBy(ncbList, (o) => o.discountRate);

  const disableNcbSlab =
    temp_data?.tab === "tab2" || tempData.policyType === "Third-party";

  let date = new Date();
  date.setDate(date.getDate() - 91);

  const ncbvoid =
    differenceInDays(
      toDate(moment().format("DD-MM-YYYY")),
      toDate(temp_data?.expiry)
    ) <= 90;

  ///-----------setting existing ncbs from temp data of home slice-------------------------

  useEffect(() => {
    if (temp_data?.ncb) {
      setValue("existinNcb", temp_data?.ncb);
      setNcbState(temp_data?.ncb);
    }
  }, [temp_data?.ncb]);

  useEffect(() => {
    if (ncbValue) {
      setNcbState(ncbValue);
    }
  }, [ncbValue]);

  //-----------------handling on submit of ncbs popup----------------------

  const onSubmit = (data) => {
    dispatch(CancelAll(true)); // cancel all apis loading (quotes apis)
    dispatch(
      set_temp_data({
        ncb:
        tempData.policyType !== "Third-party" && (expPolicy === "yes" || OwnerShip === "yes" || !ncbvoid)
            ? ncbState
            : ncbState
            ? ncbState
            : "0%",
        expPolicy: expPolicy,
        newNcb:
          expPolicy === "yes" || OwnerShip === "yes" || !ncbvoid || tempData.policyType === "Third-party"
            ? "0%"
            : ncbState
            ? ncbState === "50%"
              ? "50%"
              : temp_data?.prevShortTerm * 1
              ? ncbState
              : getNewNcb(ncbState)
            : "0%",
        noClaimMade: noClaim || expPolicy === "no" ? true : false,
        carOwnership: OwnerShip === "yes" ? true : false,
        isNcbVerified: "Y", // this removed the ncb assumptions
        breakIn: temp_data?.breakIn,
        expiry: temp_data?.expiry,
      })
    );

    dispatch(CancelAll(false)); // resetting cancel all apis loading so quotes will restart (quotes apis)
    onClose(false);
  };

  ///-----------------handling claim conditions----------------------

  useEffect(() => {
    if (expPolicy === "no" || temp_data?.noClaimMade === true) {
      setNoClaim(true);
    } else {
      setNoClaim(false);
    }
    if (expPolicy === "yes") {
      setNoClaim(false);
    }

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [expPolicy, temp_data?.noClaimMade]);

  const getNewNcb = (ncb) => {
    switch (ncb) {
      case "0%":
        return "20%";
      case "20%":
        return "25%";
      case "25%":
        return "35%";
      case "35%":
        return "45%";
      case "45%":
        return "50%";
      case "50%":
        return "50%";
      default:
        return "20%";
    }
  };

  useEffect(() => {
    if (lessthan767 && show) {
      setTimeout(() => {
        setDrawer(true);
      }, 50);
    }
  }, [show]);

  const content = (
    <>
      <Conatiner>
        <Row>
          <PaymentTermTitle>
            No Claim Bonus (NCB) Value
            <span
              className="cardTooltipSvg"
              data-toggle="popover"
              title=""
              data-content="Insured Value (IDV) Text"
              data-original-title="Insured Value (IDV)"
            >
              <CustomTooltip
                rider="true"
                id="RiderInbuilt__Tooltip"
                place={"bottom"}
                customClassName="mt-3 riderPageTooltip "
                allowClick
              >
                <img
                  data-tip="<h3 >No claim Bonus</h3> <div>No Claim Bonus (NCB) is a reward given by an insurer to an insured person for not raising any claim requests during a policy year.</div>"
                  data-html={true}
                  data-for="RiderInbuilt__Tooltip"
                  src={tooltip}
                  alt="tooltip"
                  className="toolTipRiderChild"
                />
              </CustomTooltip>
            </span>
          </PaymentTermTitle>
          <PopupSubTitle>
            No Claim Bonus or NCB is a reward given by an insurance company to
            an insured for not raising any claim requests during a policy year.
            The NCB discount is applicable on the premium amount.
          </PopupSubTitle>

          {(temp_data?.corporateVehiclesQuoteRequest?.isRenewal !== "Y" ||
            temp_data?.renewalAttributes?.ownership) && (
            <VehicleOwnership temp_data={temp_data} register={register} />
          )}
          {(temp_data?.corporateVehiclesQuoteRequest?.isRenewal !== "Y" ||
            temp_data?.renewalAttributes?.claim) && (
            <ClaimsMade temp_data={temp_data} register={register} />
          )}
          {temp_data?.expiry &&
          OwnerShip !== "yes" &&
          ncbvoid &&
          !disableNcbSlab &&
          expPolicy !== "yes" ? (
            <ExistingNcb
              temp_data={temp_data}
              myOrderedNcbList={myOrderedNcbList}
              ncbValue={ncbValue}
              register={register}
              lessthan767={lessthan767}
              getNewNcb={getNewNcb}
            />
          ) : disableNcbSlab ? (
            <></>
          ) : (
            <EligibilityMessage
              expPolicy={expPolicy}
              OwnerShip={OwnerShip}
              ncbvoid={ncbvoid}
            />
          )}
          <PaymentTermRadioWrap>
            <ApplyButton onClick={() => [_haptics([100, 0, 50]), onSubmit()]}>
              APPLY
            </ApplyButton>
          </PaymentTermRadioWrap>
        </Row>
      </Conatiner>
    </>
  );
  return !lessthan767 ? (
    <Popup
      height={lessthan450 ? "100%" : "auto"}
      width={lessthan450 ? "100%" : "400px"}
      show={show}
      onClose={onClose}
      content={content}
      position="middle"
      top="top"
      left={lessthan963 ? "50%" : "80%"}
    />
  ) : (
    <>
      <React.Fragment key={"bottom"} style={{ borderRadius: "5% 5% 0% 0%" }}>
        <Drawer
          anchor={"bottom"}
          open={drawer}
          onClose={() => {
            setDrawer(false);
            onClose(false);
          }}
          onOpen={() => setDrawer(true)}
          ModalProps={{
            keepMounted: true,
          }}
        >
          <MobileDrawerBody>
            <CloseButton
              onClick={() => {
                setDrawer(false);
                onClose(false);
              }}
            >
              <svg
                version="1.1"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                style={{ height: " 25px" }}
              >
                <path
                  fill={"#000"}
                  d="M12,2c-5.53,0 -10,4.47 -10,10c0,5.53 4.47,10 10,10c5.53,0 10,-4.47 10,-10c0,-5.53 -4.47,-10 -10,-10Zm5,13.59l-1.41,1.41l-3.59,-3.59l-3.59,3.59l-1.41,-1.41l3.59,-3.59l-3.59,-3.59l1.41,-1.41l3.59,3.59l3.59,-3.59l1.41,1.41l-3.59,3.59l3.59,3.59Z"
                ></path>
                <path fill="none" d="M0,0h24v24h-24Z"></path>
              </svg>
            </CloseButton>
            {content}
          </MobileDrawerBody>
        </Drawer>
      </React.Fragment>

      <GlobalStyle />
    </>
  );
};

// PropTypes
NCBPopup.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
};

// DefaultTypes
NCBPopup.defaultProps = {
  show: false,
  onClose: () => {},
};

export default NCBPopup;
