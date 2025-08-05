import React from "react";
import "./compare-container.scss";
import { ComparePlan } from "./compare-plans";
import _ from "lodash";
import styled from "styled-components";
import { setCompareData } from "../quote.slice";
import { useDispatch, useSelector } from "react-redux";
import { useHistory } from "react-router";
import { useLocation } from "react-router";
import CancelIcon from "@material-ui/icons/Cancel";
import { useMediaPredicate } from "react-media-hook";
import { fetchToken } from "utils";
import { _planTracking } from "analytics/compare-page/compare-tracking";
import { TypeReturn } from "modules/type";

export const CompareContainer = ({
  CompareData,
  type,
  setMobileComp,
  addOnsAndOthers,
}) => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const dispatch = useDispatch();
  const history = useHistory();
  const location = useLocation();
  const { temp_data } = useSelector((state) => state.home);
  const { quoteComprehesive, shortTermType } = useSelector(
    (state) => state.quotes
  );
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const typeId = query.get("typeid");
  const journey_type = query.get("journey_type");
  const shared = query.get("shared");
  const _stToken = fetchToken();
  //Toggle b/w short term and comp
  const quoteCompFill = shortTermType ? shortTermType : quoteComprehesive;
  const filteredIC = CompareData?.map((item) => item?.company_alias);
  const FilteredICPackages = quoteCompFill?.filter((item) =>
    filteredIC?.includes(item?.company_alias)
  );

  const getCompareBox = (quote) => (
    <ComparePlan
      key={quote?.policyId}
      id={quote?.policyId}
      logoUrl={quote?.companyLogo}
      plan={quote?.companyName}
    />
  );
  const handleClick = () => {
    //Analytics | Compared plans | Other details of compared quotes
    _planTracking(
      CompareData,
      temp_data,
      TypeReturn(type),
      addOnsAndOthers?.selectedAddons
    );

    dispatch(
      setCompareData({
        ...CompareData,
        enquiry_id: enquiry_id,
        userProductJourneyId: enquiry_id,
        addOnsAndOthers: JSON.stringify(addOnsAndOthers),
        quotePackages: FilteredICPackages,
      })
    );

    history.push(
      `/${type}/compare-quote?enquiry_id=${enquiry_id}${
        token ? `&xutm=${token}` : ``
      }${typeId ? `&typeid=${typeId}` : ``}${
        journey_type ? `&journey_type=${journey_type}` : ``
      }${_stToken ? `&stToken=${_stToken}` : ``}${
        shared ? `&shared=${shared}` : ``
      }`
    );
  };

  const handleClose = () => {
    if (!_.isEmpty(CompareData)) {
      CompareData.forEach((quote) => {
        if (
          document.getElementById(`reviewAgree${quote?.policyId}`) &&
          quote?.policyId
        ) {
          setMobileComp(false);
          document.getElementById(`reviewAgree${quote?.policyId}`).click();
        }
      });
    }
  };

  return (
    <div className="quote-page__quote-cards--on-compare">
      <div
        className="compare-container-wrap"
        // style={{ backdropFilter: "blur(20px) !important" }}
      >
        <div className="compare-container">
          <div className="compare-boxes w-100">
            {!_.isEmpty(CompareData)
              ? CompareData?.map((item) => getCompareBox(item))
              : getCompareBox({})}
            {CompareData?.length === 1 && (
              <>
                <CompareBox>
                  <AddPlanIcon
                    className="fa fa-plus"
                    style={{
                      width: "30px",
                      height: "50px",
                      display: "flex",
                      justifyContent: "center",
                      alignItems: "center",
                      border: "none",
                      marginRight: "5px",
                    }}
                  ></AddPlanIcon>
                  Add Plan
                </CompareBox>
                <CompareBox>
                  <AddPlanIcon
                    className="fa fa-plus"
                    style={{
                      width: "30px",
                      height: "50px",
                      display: "flex",
                      justifyContent: "center",
                      alignItems: "center",
                      border: "none",
                      marginRight: "5px",
                    }}
                  ></AddPlanIcon>
                  Add Plan
                </CompareBox>
              </>
            )}
            {CompareData?.length === 2 && (
              <CompareBox>
                <AddPlanIcon
                  className="fa fa-plus"
                  style={{
                    width: "30px",
                    height: "50px",
                    display: "flex",
                    justifyContent: "center",
                    alignItems: "center",
                    border: "none",
                    marginRight: "5px",
                  }}
                ></AddPlanIcon>
                Add Plan
              </CompareBox>
            )}
          </div>
          <CompareContainerButton
            style={{
              cursor:
                CompareData?.length < 2 || temp_data?.tab === "tab2"
                  ? "not-allowed"
                  : "pointer",
            }}
            className={`${
              CompareData?.length < 2 || temp_data?.tab === "tab2"
                ? "btn--disabled"
                : "btn--highlighted"
            }`}
            onClick={handleClick}
            disabled={CompareData?.length < 2 || temp_data?.tab === "tab2"}
          >
            {" "}
            COMPARE{" "}
          </CompareContainerButton>
          <CloseButton onClick={handleClose}>
            <b style={{ cursor: "pointer" }}>Close</b>
          </CloseButton>
        </div>
      </div>
    </div>
  );
};
const CompareContainerButton = styled.button`
  background-color: ${({ theme }) => theme.QuotePopups?.color2 || "#060"};
  border: ${({ theme }) => theme.QuotePopups?.border2 || "solid 1px #060"};
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 16px;
  color: #ffff;
  position: "absolute";
  @media screen and (max-width: 766px) {
    width: 65vw !important;
  }

  &:hover {
    background: #fff;
    border: ${({ theme }) =>
      theme.floatButton?.floatBorder || "1px solid #060"};
    color: ${({ theme }) => theme.floatButton?.floatColor || "#060"};
  }
`;

const CloseButton = styled.div`
  margin-left: 25px;
  @media screen and (max-width: 766px) {
    height: 56px;
    font-size: 16px;
    position: absolute;
    bottom: 13px;
    width: 20vw;
    right: 16px;
    // border: 1px solid gray;
    cursor: pointer;
    margin-left: unset;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
  }
`;

const CompareBox = styled.div`
  display: flex;
  justify-content: center;
  color: rgb(189 189 189);
  align-items: center;
  background-color: #f1f2f6;
  border-radius: 4px;
  border: 1px solid rgb(189 189 189) !important;
  width: 190px;
  height: 68px;
  padding: 20px 12px;
  @media (max-width: 767px) {
    height: 58px;
    width: calc(50% - 6px);
  }
`;

const AddPlanIcon = styled.i`
  color: ${({ theme }) => "rgb(189 189 189)"};
  border: ${({ theme }) => theme.QuotePopups?.border || "1px solid #bdd400"};
`;
