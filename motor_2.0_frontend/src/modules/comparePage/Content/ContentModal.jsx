import React from "react";
import { currencyFormater } from "utils";
import _ from "lodash";
import CancelIcon from "@material-ui/icons/Cancel";
import PropTypes from "prop-types";
import {
  CardDiv,
  CompareButton,
  NoPlansDiv,
  StyledDiv2,
  TopPop2,
} from "../ComparePageStyle";

const ContentModal = ({
  innerHeight,
  lessThan768,
  compareQuotesList,
  validQuote,
  tempData,
  compareFn2,
  removeFn,
  closePopup,
}) => {

  return (
    <TopPop2 innerHeight={innerHeight}>
      <div style={{ position: "relative" }}>
        <div
          style={{
            position: "sticky",
            top: "0",
            background: "#fff",
            zIndex: "999",
            // paddingBottom: "20px",
            boxShadow: "rgb(0 0 0 / 24%) 0px 8px 6px -6px",
          }}
        >
          <h4
            className="add_plans"
            style={{
              // paddingLeft: lessThan768 ? "" : "40px",
              paddingBottom: "20px",
              // paddingTop: lessThan768 ? "20px" : "",
              fontSize: "15px",
              textAlign: "left",
              marginLeft: "15px",
              paddingTop: "15px",
            }}
          >
            Add upto 3 plans to compare
          </h4>
          <div
            className="row"
            style={{
              // borderBottom: "1px dotted black",
              // paddingBottom: "20px",
              width: "100%",
              // padding: "0px 20px 20px 20px",
              display:"flex",
              gap:"10px",
              paddingLeft: "15px",
            }}
          >
            {compareQuotesList?.map((item) =>
              item.idv ? (
                <div className="col-3">
                  <CardDiv
                    style={{
                      position: "relative",
                      borderRadius: "5px",
                      width: "150%",
                      // height: "100%",
                      // padding: "10px 4px",
                      marginLeft:"10px",
                      padding:"10px",
                      display: "none",
                      flexDirection: "column",
                      justifyContent: "center",
                      alignItems: "center",
                      // margin: "0px 20px",
                      // boxShadow: "rgb(0 131 10 / 18%) 0px 5px 20px 0px",
                    }}
                  >
                    {validQuote?.length > 1 && (
                      <CancelIcon
                        onClick={() => removeFn(item)}
                        // onClick={() => {
                        //   compareFn2(item);
                        // }}
                        style={{
                          position: "absolute",
                          top: lessThan768 ? "-10px" : "-12px",
                          right: lessThan768 ? "-15px" : "-17px",
                          background: "#fff",
                          borderRadius: "50%",
                          cursor: "pointer",
                          color: "black",
                        }}
                      />
                    )}
                    <div style={{ height: "90px" }}>
                      <img
                        src={item?.companyLogo}
                        alt="myImage"
                        style={{
                          width: "100%",
                          padding: "0 5px",
                          objectFit: "cover",
                        }}
                      />
                    </div>
                    {/* <p
											style={{
												fontSize: "13px",
												margin: "10px",
												textAlign: "center",
											}}
										>
											{item?.companyName}
										</p> */}

                    <div
                      style={{
                        // display: "flex",
                        display:"none",
                        justifyContent: "space-around",
                        width: "100%",
                        fontSize: "11px",
                        marginTop: "10px",
                        gap:"20px",
                        flexDirection: lessThan768 ? "column" : "",
                        alignItems: lessThan768 ? "" : "center",
                        padding: "0 5px",
                      }}
                    >
                      <p
                        style={{
                          margin: "0px",
                          display: "flex",
                          justifyContent: "space-between",
                        }}
                      >
                        <div
                          style={{
                            textAlign: "left",
                          }}
                        >
                          Base premium:
                        </div>
                        <br />
                        <strong
                          style={
                            {
                              // fontSize: "14px",
                              // position: "relative",
                              // bottom: "15px",
                            }
                          }
                        >
                          <span
                            style={
                              {
                                // fontSize: "14px",
                              }
                            }
                          >
                            ₹
                          </span>{" "}
                          {currencyFormater(item?.finalPayableAmount)}
                        </strong>
                      </p>

                      <p
                        style={{
                          margin: "0px",
                          display: "flex",
                          justifyContent: "space-between",
                        }}
                      >
                        <div style={{ textAlign: "left" }}>IDV: </div>
                        <br />
                        <strong
                          style={
                            {
                              // fontSize: "14px",
                              // position: "relative",
                              // bottom: "15px",
                            }
                          }
                        >
                          <span
                            style={
                              {
                                // fontSize: "14px",
                              }
                            }
                          >
                            ₹
                          </span>{" "}
                          {currencyFormater(item?.idv)}
                        </strong>
                      </p>
                    </div>
                  </CardDiv>
                </div>
              ) : (
                <div className={lessThan768 ? "col-3" : "col-4"}>
                  <NoPlansDiv
                    style={{
                      width: "150%",
                      height: "100%",
                      display: "none",
                      flexDirection: "column",
                      justifyContent: "center",
                      alignItems: "center",
                      margin: "0px 20px",
                    }}
                  >
                    <i
                      className="fa fa-plus"
                      style={{
                        marginBottom: "5px",
                        // boxShadow: "1px 1px 5px grey",
                        fontSize: "16px",
                        background: "#80808078",
                        width: "35px",
                        height: "35px",
                        borderRadius: "50%",
                        display: "flex",
                        justifyContent: "center",
                        alignItems: "center",
                        color: "#fff",
                      }}
                    ></i>
                    <p className="text-center">No Plans Added</p>
                  </NoPlansDiv>
                </div>
              )
            )}
          </div>
        </div>
        <div
          className="row"
          //	className="newProductList"
          style={{
            padding: "10px 30px",
            marginTop: "20px",
            position: "relative",
            height: "auto",
            overflow: "auto",
          }}
        >
          {tempData.quoteComprehesiveGrouped?.map((singleQuote, index) => (
            <div
              className="col-6"
              style={{ margin: "3px 0px", padding: "5px 15px" }}
            >
              <div
                onClick={() => {
                  if (
                    _.compact(
                      compareQuotesList?.map((x) => x.policyId)
                    )?.includes(singleQuote?.policyId)
                  ) {
                    removeFn(singleQuote);
                  } else {
                    compareFn2(singleQuote);
                  }
                }}
                style={{
                  position: "relative",
                  borderRadius: "5px",
                  width: "100%",
                  // height: "100%",
                  padding: "10px 4px",
                  display: "flex",
                  flexDirection: "column",
                  justifyContent: "center",
                  alignItems: "center",
                  // margin: "0px 20px",
                  // boxShadow: "rgb(0 131 10 / 18%) 0px 5px 20px 0px",
                  boxShadow: "1px 1px 5px grey",
                  background: "#fff !important",
                  border: "none",
                }}
              >
                <div style={{ height: "90px" }}>
                  <img
                    src={singleQuote?.companyLogo}
                    alt="myImage"
                    style={{
                      width: "100%",
                      padding: "0 5px",
                      objectFit: "cover",
                    }}
                  />
                </div>
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-around",
                    width: "100%",
                    fontSize: "10.5px",
                    marginTop: "10px",
                    flexDirection: lessThan768 ? "column" : "",
                    alignItems: lessThan768 ? "" : "center",
                    padding: "0 5px",
                  }}
                >
                  <p
                    style={{
                      margin: "0px",
                      display: "flex",
                      justifyContent: "space-between",
                    }}
                  >
                    <div
                      style={{
                        textAlign: "left",
                      }}
                    >
                      Base premium:
                    </div>
                    <br />
                    <strong
                      style={
                        {
                          // fontSize: "14px",
                          // position: "relative",
                          // bottom: "15px",
                        }
                      }
                    >
                      <span
                        style={
                          {
                            // fontSize: "14px",
                          }
                        }
                      >
                        ₹
                      </span>{" "}
                      {currencyFormater(singleQuote?.finalPayableAmount)}
                    </strong>
                  </p>

                  <p
                    style={{
                      margin: "0px",
                      display: "flex",
                      justifyContent: "space-between",
                    }}
                  >
                    <div style={{ textAlign: "left" }}>IDV: </div>
                    <br />
                    <strong
                      style={
                        {
                          // fontSize: "14px",
                          // position: "relative",
                          // bottom: "15px",
                        }
                      }
                    >
                      <span
                        style={
                          {
                            // fontSize: "14px",
                          }
                        }
                      >
                        ₹
                      </span>{" "}
                      {currencyFormater(singleQuote?.idv)}
                    </strong>
                  </p>
                </div>
                <StyledDiv2>
                  <span
                    className="group-check float-right"
                    style={{ width: "5%" }}
                  >
                    {" "}
                    {_.compact(
                      compareQuotesList?.map((x) => x.policyId)
                    )?.includes(singleQuote?.policyId) ? (
                      <i
                        style={{
                          color: "#fff",
                          // marginTop: "15px",
                          fontSize: "14px",
                          borderRadius: "50%",
                          padding: "3px",
                        }}
                        className="fa fa-check productCheck"
                      ></i>
                    ) : (
                      <noscript></noscript>
                    )}
                  </span>
                </StyledDiv2>
              </div>
            </div>
          ))}
        </div>
      </div>
      <div
        style={{
          textAlign: "center",
          position:
            tempData?.quoteComprehesiveGrouped?.length > 2
              ? "sticky"
              : "absolute",
          bottom: "0",
          right: "0",
          width: "100%",
          zIndex: "999",
        }}
      >
        <CompareButton
          onClick={closePopup}
          style={{
            width: "100%",
            fontWeight: "bold",
            border: "none",
            padding: "15px 50px",
            letterSpacing: "0.5px",
            color: "#fff",
            borderRadius: "5px",
          }}
        >
          Compare
        </CompareButton>
      </div>
    </TopPop2>
  );
};

export default ContentModal;

// PropTypes
ContentModal.propTypes = {
  innerHeight: PropTypes.string,
  lessThan768: PropTypes.bool,
  compareQuotesList: PropTypes.object,
  validQuote: PropTypes.object,
  tempData: PropTypes.object,
  compareFn2: PropTypes.func,
  removeFn: PropTypes.func,
  closePopup: PropTypes.bool,
};

// DefaultTypes
ContentModal.defaultProps = {
  compareQuotesList: [],
  validQuote: [],
  tempData: {},
  compareFn2: () => {},
  removeFn: () => {},
  closePopup: false,
};
